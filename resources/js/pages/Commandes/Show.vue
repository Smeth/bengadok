<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import OrdonnanceViewer from '@/components/OrdonnanceViewer.vue';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

const props = withDefaults(
    defineProps<{
    commande: {
        id: number;
        numero: string;
        date: string;
        heurs: string;
        status: string;
        status_pharmacie: string;
        parent_id?: number | null;
        acceptation_client?: boolean;
        prix_total: number;
        motif_annulation?: string;
        commentaire?: string;
        client: { nom: string; prenom: string; tel: string; adresse?: string; sexe?: string };
        pharmacie: { designation: string; telephone: string; adresse: string } | null;
        pharmacie_refusee?: { designation: string };
        pharmacieRefusee?: { designation: string };
        produits: Array<{
            id: number;
            designation: string;
            dosage?: string;
            pivot: { quantite: number; quantite_confirmee?: number; prix_unitaire: number; status?: string };
        }>;
        enfants?: Array<{ id: number; numero: string; pharmacie?: { designation: string }; produits: unknown[] }>;
        mode_paiement?: { designation: string };
        livreur?: { id: number; nom: string; prenom: string; tel: string };
        ordonnance?: { urlfile?: string } | null;
    };
    pharmacieOptions?: Array<{ id: number; designation: string; adresse?: string }>;
    livreurs?: Array<{ id: number; nom: string; prenom: string; tel: string }>;
    }>(),
    { livreurs: () => [] },
);

const page = usePage();
const roles = computed(() => (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? []);
const flashError = computed(() => (page.props.flash as { error?: string })?.error);
const flashStatus = computed(() => (page.props.flash as { status?: string })?.status);
const isAgent = computed(() => roles.value.includes('agent_call_center') || roles.value.includes('admin') || roles.value.includes('super_admin'));
const isVendeurOuGerant = computed(() => roles.value.includes('vendeur') || roles.value.includes('gerant'));
const peutModifier = computed(() => isAgent.value && ['nouvelle', 'en_attente'].includes(props.commande.status));
const peutRenvoyer = computed(() => isAgent.value && props.commande.status === 'en_attente');
const peutValiderRetrait = computed(() => isVendeurOuGerant.value && props.commande.status_pharmacie === 'valide_a_preparer');
const peutValiderDispo = computed(() => isVendeurOuGerant.value && props.commande.status_pharmacie === 'nouvelle');

const peutAssignerLivreur = computed(() =>
    isAgent.value && ['validee', 'a_preparer', 'retiree'].includes(props.commande.status),
);

function civiliteFromSexe(sexe?: string | null): string {
    if (sexe === 'F') return 'Mme';
    if (sexe === 'M') return 'Mr';
    return '';
}

function nomClientComplet(): string {
    const c = props.commande.client;
    if (!c) return '-';
    const prenom = (c.prenom ?? '').trim();
    const nom = (c.nom ?? '').trim();
    if (!prenom && !nom) return '-';
    const core = prenom === nom ? prenom : [prenom, nom].filter(Boolean).join(' ');
    const civ = civiliteFromSexe(c.sexe);
    return civ ? `${civ} ${core}` : core;
}

function assignLivreurShow(e: Event) {
    const sel = e.target as HTMLSelectElement;
    const v = sel.value;
    router.patch(`/commandes/${props.commande.id}/livreur`, {
        livreur_id: v ? Number(v) : null,
    });
}

const pharmacieRefusee = computed(() => props.commande.pharmacieRefusee ?? props.commande.pharmacie_refusee);

/** Produits avec quantité manquante (indisponible ou partiel) */
const produitsManquants = computed(() => {
    const produits = props.commande?.produits ?? [];
    return produits.filter((p) => {
        const pivot = p.pivot ?? { quantite: 0, quantite_confirmee: 0, status: '' };
        const qte = pivot.quantite ?? 0;
        const qteConf = pivot.quantite_confirmee ?? (pivot.status === 'indisponible' ? 0 : qte);
        return qte - qteConf > 0;
    });
});
const peutRenvoyerPartiel = computed(
    () => peutRenvoyer.value && !props.commande.parent_id && produitsManquants.value.length > 0
);

const lignesValidation = ref<Record<number, { status: string; quantite_confirmee: number }>>({});
function initLignes() {
    const produits = props.commande?.produits ?? [];
    if (!Array.isArray(produits)) return;
    produits.forEach((p) => {
        const pivot = p.pivot ?? { quantite: 0, prix_unitaire: 0 };
        const qte = pivot.quantite ?? 0;
        const status = pivot.status || 'disponible';
        const qteConf = pivot.quantite_confirmee ?? (status === 'indisponible' ? 0 : qte);
        lignesValidation.value[p.id] = {
            status,
            quantite_confirmee: status === 'indisponible' ? 0 : qteConf,
        };
    });
}
watch(() => props.commande?.produits, () => initLignes(), { immediate: true });

function renvoyerPharmacie() {
    router.post(`/agent/commande/${props.commande.id}/renvoyer-pharmacie`);
}

function toggleAcceptationClient(e: Event) {
    const checked = (e.target as HTMLInputElement).checked;
    router.patch(`/commandes/${props.commande.id}/acceptation-client`, {
        acceptation_client: checked,
    });
}

function validerRetrait() {
    router.post(`/dok-pharma/${props.commande.id}/valider-retrait`);
}

function validerDisponibilite() {
    const produits = props.commande?.produits ?? [];
    const lignes = produits.map((p) => {
        const status = lignesValidation.value[p.id]?.status ?? 'disponible';
        const qteConf = lignesValidation.value[p.id]?.quantite_confirmee ?? 0;
        return {
            produit_id: p.id,
            status,
            quantite_confirmee: status === 'indisponible' ? 0 : Math.min(Math.max(0, qteConf), p.pivot?.quantite ?? 0),
        };
    });
    router.post(`/dok-pharma/${props.commande.id}/valider`, { lignes });
}

const motifsAnnulationOptions = computed(() => page.props.motifs_annulation ?? []);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Commandes', href: '/commandes' },
    { title: '#' + props.commande.numero, href: '#' },
];

const modalAnnulation = ref(false);
const motifSelectionne = ref<string>('');

function onStatusChange(newStatus: string) {
    if (newStatus === 'annulee') {
        motifSelectionne.value = '';
        modalAnnulation.value = true;
    } else {
        router.patch(`/commandes/${props.commande.id}/status`, { status: newStatus });
    }
}

function confirmerAnnulation() {
    router.patch(`/commandes/${props.commande.id}/status`, {
        status: 'annulee',
        motif_annulation: motifSelectionne.value || undefined,
    });
    modalAnnulation.value = false;
}

const modalRenvoyerPartiel = ref(false);
const pharmacieSelectionnee = ref<string | number>('');
const lignesPartiel = ref<Record<number, { selected: boolean; quantite: number }>>({});

watch(produitsManquants, (prods) => {
    const init: Record<number, { selected: boolean; quantite: number }> = {};
    prods.forEach((p) => {
        const pivot = p.pivot ?? { quantite: 0, quantite_confirmee: 0 };
        const manquant = (pivot.quantite ?? 0) - (pivot.quantite_confirmee ?? (pivot.status === 'indisponible' ? 0 : pivot.quantite ?? 0));
        init[p.id] = { selected: true, quantite: Math.max(1, manquant) };
    });
    lignesPartiel.value = init;
}, { immediate: true });

function ouvrirModalRenvoyerPartiel() {
    pharmacieSelectionnee.value = props.pharmacieOptions?.[0]?.id ?? '';
    modalRenvoyerPartiel.value = true;
}

function renvoyerPharmaciePartiel() {
    const phId = Number(pharmacieSelectionnee.value);
    if (!phId) return;
    const lignes = Object.entries(lignesPartiel.value)
        .filter(([, v]) => v.selected && v.quantite > 0)
        .map(([produitId, v]) => ({ produit_id: Number(produitId), quantite: v.quantite }));
    if (lignes.length === 0) return;
    router.post(`/agent/commande/${props.commande.id}/renvoyer-pharmacie-partiel`, {
        pharmacie_id: phId,
        lignes,
    });
    modalRenvoyerPartiel.value = false;
}
</script>

<template>
    <Head :title="`Commande ${commande.numero} - BengaDok`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Commande {{ commande.numero }}</h1>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="peutRenvoyer && !peutRenvoyerPartiel"
                        variant="default"
                        @click="renvoyerPharmacie"
                    >
                        Renvoyer tout à 2ème pharmacie
                    </Button>
                    <Button
                        v-if="peutRenvoyerPartiel"
                        variant="outline"
                        @click="ouvrirModalRenvoyerPartiel"
                    >
                        Renvoyer partiel (produits manquants)
                    </Button>
                    <Button
                        v-if="peutRenvoyer && peutRenvoyerPartiel"
                        variant="default"
                        @click="renvoyerPharmacie"
                    >
                        Renvoyer tout
                    </Button>
                    <Button
                        v-if="peutValiderRetrait"
                        variant="default"
                        @click="validerRetrait"
                    >
                        Valider le retrait (livreur)
                    </Button>
                    <select
                        v-if="isAgent"
                        :value="commande.status"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        @change="onStatusChange(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="nouvelle">Nouvelle</option>
                        <option value="en_attente">En attente</option>
                        <option value="validee">Validée</option>
                        <option v-if="commande.status_pharmacie === 'livre'" value="retiree">Livrée</option>
                        <option value="annulee">Annulée</option>
                    </select>
                    <Link v-if="peutModifier" :href="`/commandes/${commande.id}/edit`">
                        <Button variant="outline">Modifier</Button>
                    </Link>
                    <Link href="/commandes">
                        <Button variant="outline">Retour</Button>
                    </Link>
                </div>

                <Dialog :open="modalAnnulation" @update:open="modalAnnulation = $event">
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Motif d'annulation</DialogTitle>
                        </DialogHeader>
                        <p class="text-sm text-muted-foreground mb-4">
                            Veuillez indiquer le motif de l'annulation de cette commande.
                        </p>
                        <select
                            v-model="motifSelectionne"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Sélectionner un motif</option>
                            <option
                                v-for="m in motifsAnnulationOptions"
                                :key="m.slug"
                                :value="m.slug"
                            >
                                {{ m.label }}
                            </option>
                        </select>
                        <DialogFooter class="mt-4">
                            <Button variant="outline" @click="modalAnnulation = false">
                                Annuler
                            </Button>
                            <Button :disabled="!motifSelectionne" @click="confirmerAnnulation()">
                                Confirmer l'annulation
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog :open="modalRenvoyerPartiel" @update:open="modalRenvoyerPartiel = $event">
                    <DialogContent class="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Renvoyer les produits manquants à une 2ème pharmacie</DialogTitle>
                        </DialogHeader>
                        <p class="text-sm text-muted-foreground mb-4">
                            Sélectionnez les produits à renvoyer et la pharmacie de destination.
                        </p>
                        <div class="space-y-3 mb-4">
                            <div
                                v-for="p in produitsManquants"
                                :key="p.id"
                                class="flex items-center gap-3 rounded-lg border p-2"
                            >
                                <input
                                    v-model="lignesPartiel[p.id].selected"
                                    type="checkbox"
                                    class="rounded"
                                />
                                <span class="flex-1">{{ p.designation }} {{ p.dosage ?? '' }}</span>
                                <span class="text-muted-foreground text-sm">Manquant :</span>
                                <Input
                                    v-model.number="lignesPartiel[p.id].quantite"
                                    type="number"
                                    :min="1"
                                    :max="(p.pivot?.quantite ?? 0) - (p.pivot?.quantite_confirmee ?? 0)"
                                    class="w-16"
                                />
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="mb-2 block text-sm font-medium">Pharmacie de destination</label>
                            <select
                                v-model="pharmacieSelectionnee"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Choisir une pharmacie</option>
                                <option
                                    v-for="ph in (pharmacieOptions ?? [])"
                                    :key="ph.id"
                                    :value="ph.id"
                                >
                                    {{ ph.designation }} {{ ph.adresse ? `- ${ph.adresse}` : '' }}
                                </option>
                            </select>
                        </div>
                        <DialogFooter>
                            <Button variant="outline" @click="modalRenvoyerPartiel = false">
                                Annuler
                            </Button>
                            <Button @click="renvoyerPharmaciePartiel">
                                Renvoyer
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <div v-if="flashError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ flashError }}
            </div>
            <div v-if="flashStatus" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ flashStatus }}
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border p-4">
                    <h3 class="mb-3 font-semibold">Client</h3>
                    <p>{{ nomClientComplet() }}</p>
                    <p class="text-muted-foreground">{{ commande.client?.tel }}</p>
                    <p class="text-muted-foreground">{{ commande.client?.adresse }}</p>
                </div>
                <div v-if="isAgent && commande.status === 'en_attente'" class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                    <h3 class="mb-3 font-semibold">Validation client</h3>
                    <p class="mb-3 text-sm text-muted-foreground">
                        Le client doit avoir validé le coût total (produits + livraison) avant de valider la commande.
                    </p>
                    <div class="flex items-center gap-3">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                :checked="commande.acceptation_client"
                                class="rounded"
                                @change="toggleAcceptationClient($event)"
                            />
                            <span>Client a validé le coût total</span>
                        </label>
                    </div>
                </div>
                <div
                    v-if="commande.livreur || peutAssignerLivreur"
                    class="rounded-xl border p-4 lg:col-span-2"
                >
                    <h3 class="mb-3 font-semibold">Livreur</h3>
                    <div v-if="peutAssignerLivreur" class="max-w-md">
                        <label class="mb-2 block text-sm font-medium text-muted-foreground" for="show-livreur-select">
                            Attribuer un livreur
                        </label>
                        <select
                            id="show-livreur-select"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            :value="commande.livreur?.id ?? ''"
                            @change="assignLivreurShow($event)"
                        >
                            <option value="">Aucun livreur</option>
                            <option v-for="l in livreurs" :key="l.id" :value="l.id">
                                {{ l.prenom }} {{ l.nom }} — {{ l.tel }}
                            </option>
                        </select>
                    </div>
                    <template v-else-if="commande.livreur">
                        <p>{{ commande.livreur.prenom }} {{ commande.livreur.nom }}</p>
                        <p class="text-muted-foreground">{{ commande.livreur.tel }}</p>
                    </template>
                </div>

                <div class="rounded-xl border p-4">
                    <h3 class="mb-3 font-semibold">Pharmacie</h3>
                    <p>{{ commande.pharmacie?.designation }}</p>
                    <p class="text-muted-foreground">{{ commande.pharmacie?.telephone }}</p>
                    <p class="text-muted-foreground">{{ commande.pharmacie?.adresse }}</p>
                    <p v-if="pharmacieRefusee" class="mt-2 text-sm text-amber-600">
                        Pharmacie refusée précédente : {{ pharmacieRefusee.designation }}
                    </p>
                </div>
            </div>

            <div v-if="peutValiderDispo" class="rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                <h3 class="mb-3 font-semibold">Vérifier disponibilité des médicaments</h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    Pour chaque produit : indiquez le statut et précisez la quantité disponible de votre côté.
                </p>
                <div class="space-y-3">
                    <div
                        v-for="p in (commande.produits ?? [])"
                        :key="p.id"
                        class="flex flex-wrap items-center gap-4 rounded-lg border bg-white p-3"
                    >
                        <span class="flex-1 font-medium">{{ p.designation }} {{ p.dosage ?? '' }}</span>
                        <span class="text-muted-foreground">Demandé : {{ p.pivot.quantite }}</span>
                        <select
                            v-model="lignesValidation[p.id].status"
                            class="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                        >
                            <option value="disponible">Disponible</option>
                            <option value="indisponible">Indisponible</option>
                            <option value="partiel">Partiel</option>
                        </select>
                        <div v-if="lignesValidation[p.id]?.status !== 'indisponible'" class="flex items-center gap-2">
                            <Input
                                v-model.number="lignesValidation[p.id].quantite_confirmee"
                                type="number"
                                :min="0"
                                :max="p.pivot.quantite"
                                class="w-20"
                            />
                            <span class="text-sm">qté dispo</span>
                        </div>
                    </div>
                </div>
                <Button class="mt-4" @click="validerDisponibilite">
                    Envoyer à l'agent
                </Button>
            </div>

            <div class="rounded-xl border p-4">
                <h3 class="mb-3 font-semibold">Ordonnance</h3>
                <OrdonnanceViewer
                    v-if="commande.ordonnance?.urlfile"
                    :urlfile="commande.ordonnance.urlfile"
                    max-height="16rem"
                />
                <p v-else class="py-6 text-center text-muted-foreground">Aucune ordonnance</p>
            </div>

            <div class="rounded-xl border p-4">
                <h3 class="mb-3 font-semibold">Médicaments</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="py-2 text-left">Produit</th>
                            <th class="py-2 text-left">Qté</th>
                            <th class="py-2 text-left">Prix unit.</th>
                            <th class="py-2 text-left">Total</th>
                            <th class="py-2 text-left">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in (commande.produits ?? [])"
                            :key="p.id"
                            class="border-b last:border-0"
                        >
                            <td class="py-2">{{ p.designation }} {{ p.dosage ?? '' }}</td>
                            <td class="py-2">{{ p.pivot.quantite }}</td>
                            <td class="py-2">{{ Number(p.pivot.prix_unitaire).toLocaleString('fr-FR') }} XAF</td>
                            <td class="py-2">
                                {{ (p.pivot.quantite * Number(p.pivot.prix_unitaire)).toLocaleString('fr-FR') }} XAF
                            </td>
                            <td class="py-2">
                                {{ p.pivot.status }}
                                <span v-if="p.pivot.status === 'partiel' && p.pivot.quantite_confirmee">
                                    ({{ p.pivot.quantite_confirmee }} qté)
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-3 font-semibold">
                    Total : {{ Number(commande.prix_total).toLocaleString('fr-FR') }} XAF
                </p>
                <div v-if="commande.enfants?.length" class="mt-4 rounded-lg border border-amber-200 bg-amber-50/30 p-4">
                    <h4 class="mb-2 font-semibold text-amber-800">Produits renvoyés à une 2ème pharmacie</h4>
                    <div
                        v-for="enf in (commande.enfants ?? [])"
                        :key="enf.id"
                        class="mb-2 rounded border bg-white p-2 text-sm"
                    >
                        <p class="font-medium text-amber-700">{{ enf.pharmacie?.designation }} ({{ enf.numero }})</p>
                        <ul class="mt-1 list-inside list-disc text-muted-foreground">
                            <li v-for="(prod, idx) in (enf.produits ?? [])" :key="idx">
                                {{ (prod as { designation?: string; dosage?: string; pivot?: { quantite: number } })?.designation }}
                                {{ (prod as { dosage?: string })?.dosage ?? '' }}
                                × {{ (prod as { pivot?: { quantite: number } })?.pivot?.quantite ?? 0 }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
