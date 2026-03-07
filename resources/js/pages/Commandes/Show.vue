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

const props = defineProps<{
    commande: {
        id: number;
        numero: string;
        date: string;
        heurs: string;
        status: string;
        acceptation_client?: boolean;
        prix_total: number;
        motif_annulation?: string;
        commentaire?: string;
        client: { nom: string; prenom: string; tel: string; adresse?: string };
        pharmacie: { designation: string; telephone: string; adresse: string } | null;
        pharmacie_refusee?: { designation: string };
        pharmacieRefusee?: { designation: string };
        produits: Array<{
            id: number;
            designation: string;
            dosage?: string;
            pivot: { quantite: number; quantite_confirmee?: number; prix_unitaire: number; status?: string };
        }>;
        mode_paiement?: { designation: string };
        livreur?: { nom: string; prenom: string; tel: string };
        ordonnance?: { urlfile?: string } | null;
    };
}>();

const page = usePage();
const roles = computed(() => (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? []);
const isAgent = computed(() => roles.value.includes('agent_call_center') || roles.value.includes('admin') || roles.value.includes('super_admin'));
const isVendeurOuGerant = computed(() => roles.value.includes('vendeur') || roles.value.includes('gerant'));
const peutRenvoyer = computed(() => isAgent.value && ['en_attente', 'indisponible_pharmacie', 'partiellement_validee'].includes(props.commande.status));
const peutValiderRetrait = computed(() => isVendeurOuGerant.value && ['validee', 'a_preparer'].includes(props.commande.status));
const peutValiderDispo = computed(() => isVendeurOuGerant.value && props.commande.status === 'nouvelle');

const pharmacieRefusee = computed(() => props.commande.pharmacieRefusee ?? props.commande.pharmacie_refusee);

const lignesValidation = ref<Record<number, { status: string; quantite_confirmee: number }>>({});
function initLignes() {
    const produits = props.commande?.produits ?? [];
    if (!Array.isArray(produits)) return;
    produits.forEach((p) => {
        const pivot = p.pivot ?? { quantite: 0, prix_unitaire: 0 };
        lignesValidation.value[p.id] = {
            status: pivot.status || 'disponible',
            quantite_confirmee: pivot.quantite_confirmee ?? pivot.quantite ?? 0,
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
    const lignes = produits.map((p) => ({
        produit_id: p.id,
        status: lignesValidation.value[p.id]?.status ?? 'disponible',
        quantite_confirmee: lignesValidation.value[p.id]?.quantite_confirmee ?? (p.pivot?.quantite ?? 0),
    }));
    router.post(`/dok-pharma/${props.commande.id}/valider`, { lignes });
}

const MOTIFS_ANNULATION: Record<string, string> = {
    medicaments_indisponibles: 'Médicaments indisponibles',
    demande_patient: 'Demande du patient',
    erreur_commande: 'Erreur de commande',
    probleme_paiement: 'Problème de paiement',
};

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
</script>

<template>
    <Head :title="`Commande ${commande.numero} - BengaDok`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Commande {{ commande.numero }}</h1>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="peutRenvoyer"
                        variant="default"
                        @click="renvoyerPharmacie"
                    >
                        Renvoyer à 2ème pharmacie
                    </Button>
                    <Button
                        v-if="peutValiderRetrait"
                        variant="default"
                        @click="validerRetrait"
                    >
                        Valider le retrait (livreur)
                    </Button>
                    <select
                        :value="commande.status"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        @change="onStatusChange(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="nouvelle">Nouvelle</option>
                        <option value="en_attente">En attente</option>
                        <option value="validee">Validée</option>
                        <option value="partiellement_validee">Partiellement validée</option>
                        <option value="indisponible_pharmacie">Indisponible (pharmacie)</option>
                        <option value="a_preparer">À préparer</option>
                        <option value="retiree">Retirée</option>
                        <option value="livree">Livrée</option>
                        <option value="annulee">Annulée</option>
                    </select>
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
                                v-for="(label, value) in MOTIFS_ANNULATION"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <DialogFooter class="mt-4">
                            <Button variant="outline" @click="modalAnnulation = false">
                                Annuler
                            </Button>
                            <Button @click="confirmerAnnulation()">
                                Confirmer l'annulation
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border p-4">
                    <h3 class="mb-3 font-semibold">Client</h3>
                    <p>{{ [commande.client?.prenom, commande.client?.nom].filter(Boolean).join(' ') || '-' }}</p>
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
                    Indiquez pour chaque produit : disponible, indisponible, ou partiel (quantité partielle).
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
                        <Input
                            v-if="lignesValidation[p.id]?.status === 'partiel'"
                            v-model.number="lignesValidation[p.id].quantite_confirmee"
                            type="number"
                            :min="1"
                            :max="p.pivot.quantite"
                            class="w-20"
                        />
                        <span v-if="lignesValidation[p.id]?.status === 'partiel'" class="text-sm">qté dispo</span>
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
            </div>
        </div>
    </AppLayout>
</template>
