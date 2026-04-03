<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import OrdonnanceFilePreview from '@/components/OrdonnanceFilePreview.vue';
import OrdonnanceUppy from '@/components/OrdonnanceUppy.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    commande: {
        id: number;
        numero: string;
        date: string;
        heurs: string;
        commentaire?: string;
        beneficiaire?: string;
        client: {
            id: number;
            nom: string;
            prenom: string;
            tel: string;
            adresse?: string;
        };
        pharmacie: {
            id: number;
            designation: string;
            zone?: { designation: string };
            adresse?: string;
        };
        produits: Array<{
            id: number;
            designation: string;
            dosage?: string;
            pivot: { quantite: number; prix_unitaire: number };
        }>;
        mode_paiement?: { id: number; designation: string };
        montant_livraison?: { id: number; designation: number };
    };
    pharmacies: Array<{
        id: number;
        designation: string;
        zone?: { designation: string };
        adresse?: string;
    }>;
    modesPaiement: Array<{ id: number; designation: string }>;
    montantsLivraison: Array<{ id: number; designation: number }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Commandes', href: '/commandes' },
    {
        title: '#' + props.commande.numero,
        href: `/commandes/${props.commande.id}`,
    },
    { title: 'Modifier', href: '#' },
];

const clientId = ref(props.commande.client?.id ?? '');
const clientNom = ref(props.commande.client?.nom ?? '');
const clientPrenom = ref(props.commande.client?.prenom ?? '');
const clientTel = ref(props.commande.client?.tel ?? '');
const clientAdresse = ref(props.commande.client?.adresse ?? '');
const pharmacieId = ref(props.commande.pharmacie?.id ?? '');
const date = ref(props.commande.date?.slice(0, 10) ?? '');
const heurs = ref(props.commande.heurs ?? '08:00');
const beneficiaire = ref(props.commande.beneficiaire ?? '');
const commentaire = ref(props.commande.commentaire ?? '');
const modePaiementId = ref(props.commande.mode_paiement?.id ?? '');
const montantLivraisonId = ref(props.commande.montant_livraison?.id ?? '');
const ordonnanceFile = ref<File | null>(null);

type ProduitLigne = {
    id?: number;
    designation: string;
    dosage: string;
    quantite: number;
    prix_unitaire: number;
};
const produitsSelection = ref<ProduitLigne[]>([]);

watch(
    () => props.commande?.produits,
    () => {
        produitsSelection.value = (props.commande?.produits ?? []).map((p) => ({
            id: p.id,
            designation: p.designation ?? '',
            dosage: p.dosage ?? '',
            quantite: p.pivot?.quantite ?? 1,
            prix_unitaire: Number(p.pivot?.prix_unitaire) ?? 0,
        }));
    },
    { immediate: true },
);

function ajouterProduit() {
    produitsSelection.value.push({
        designation: '',
        dosage: '',
        quantite: 1,
        prix_unitaire: 0,
    });
}

function supprimerProduit(i: number) {
    produitsSelection.value.splice(i, 1);
}

function submit() {
    const produitsValides = produitsSelection.value
        .filter(
            (p) =>
                p.designation.trim() &&
                p.quantite > 0 &&
                Number(p.prix_unitaire) >= 0,
        )
        .map((p) => ({
            id: p.id,
            designation: p.designation.trim(),
            dosage: (p.dosage ?? '').trim() || null,
            quantite: p.quantite,
            prix_unitaire: Number(p.prix_unitaire),
        }));
    if (!produitsValides.length) return;

    const payload: Record<string, unknown> = {
        client_id: clientId.value || undefined,
        client_nom: clientNom.value.trim(),
        client_prenom: clientPrenom.value.trim(),
        client_tel: clientTel.value.trim(),
        client_adresse: clientAdresse.value.trim(),
        pharmacie_id: pharmacieId.value || undefined,
        date: date.value,
        heurs: heurs.value,
        beneficiaire: beneficiaire.value.trim(),
        produits: produitsValides,
        mode_paiement_id: modePaiementId.value || undefined,
        montant_livraison_id: montantLivraisonId.value || undefined,
        commentaire: commentaire.value.trim(),
    };

    if (ordonnanceFile.value) {
        const formData = new FormData();
        Object.entries(payload).forEach(([k, v]) => {
            if (v !== undefined && v !== '' && k !== 'produits')
                formData.append(k, String(v));
        });
        formData.append('produits', JSON.stringify(produitsValides));
        formData.append('ordonnance', ordonnanceFile.value);
        formData.append('_method', 'PATCH');
        router.post(`/commandes/${props.commande.id}`, formData, {
            forceFormData: true,
        });
    } else {
        router.patch(`/commandes/${props.commande.id}`, payload);
    }
}
</script>

<template>
    <Head :title="`Modifier commande ${commande.numero} - BengaDok`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-1 flex-col gap-6 p-4" @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">
                    Modifier la commande {{ commande.numero }}
                </h1>
                <Link :href="`/commandes/${commande.id}`">
                    <Button variant="outline">Annuler</Button>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-4">
                    <h3 class="font-semibold">Client</h3>
                    <div class="space-y-2">
                        <Label>Nom *</Label>
                        <Input v-model="clientNom" required placeholder="Nom" />
                    </div>
                    <div class="space-y-2">
                        <Label>Prénom</Label>
                        <Input v-model="clientPrenom" placeholder="Prénom" />
                    </div>
                    <div class="space-y-2">
                        <Label>Téléphone *</Label>
                        <Input
                            v-model="clientTel"
                            required
                            placeholder="Téléphone"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Adresse *</Label>
                        <Input
                            v-model="clientAdresse"
                            required
                            placeholder="Adresse"
                        />
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="font-semibold">Commande</h3>
                    <div class="space-y-2">
                        <Label>Pharmacie *</Label>
                        <select
                            v-model="pharmacieId"
                            required
                            class="w-full rounded-md border border-input bg-background px-3 py-2"
                        >
                            <option value="">Choisir une pharmacie</option>
                            <option
                                v-for="p in pharmacies"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.designation }} ({{ p.zone?.designation }})
                                - {{ p.adresse }}
                            </option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Date *</Label>
                            <Input v-model="date" type="date" required />
                        </div>
                        <div class="space-y-2">
                            <Label>Heure *</Label>
                            <Input v-model="heurs" type="time" required />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label>Bénéficiaire</Label>
                        <Input
                            v-model="beneficiaire"
                            placeholder="Bénéficiaire"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Commentaire</Label>
                        <Input
                            v-model="commentaire"
                            placeholder="Commentaire"
                        />
                    </div>
                </div>
            </div>

            <div>
                <Label>Médicaments *</Label>
                <div class="mt-2 space-y-2">
                    <div
                        v-for="(p, i) in produitsSelection"
                        :key="i"
                        class="flex flex-wrap items-end gap-2"
                    >
                        <Input
                            v-model="p.designation"
                            placeholder="Désignation"
                            class="min-w-[120px] flex-1"
                        />
                        <Input
                            v-model="p.dosage"
                            placeholder="Dosage"
                            class="w-24"
                        />
                        <Input
                            v-model.number="p.quantite"
                            type="number"
                            min="1"
                            class="w-16"
                            placeholder="Qté"
                        />
                        <Input
                            v-model.number="p.prix_unitaire"
                            type="number"
                            min="0"
                            class="w-24"
                            placeholder="Prix"
                        />
                        <Button
                            type="button"
                            variant="destructive"
                            size="icon"
                            @click="supprimerProduit(i)"
                            >×</Button
                        >
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        @click="ajouterProduit"
                        >+ Ajouter médicament</Button
                    >
                </div>
            </div>

            <div>
                <OrdonnanceUppy
                    v-model="ordonnanceFile"
                    label="Nouvelle ordonnance (remplace l'actuelle)"
                />
                <OrdonnanceFilePreview
                    v-if="ordonnanceFile"
                    :file="ordonnanceFile"
                    class="mt-3"
                    max-height="14rem"
                />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <Label>Mode de paiement</Label>
                    <select
                        v-model="modePaiementId"
                        class="w-full rounded-md border border-input bg-background px-3 py-2"
                    >
                        <option value="">—</option>
                        <option
                            v-for="m in modesPaiement"
                            :key="m.id"
                            :value="m.id"
                        >
                            {{ m.designation }}
                        </option>
                    </select>
                </div>
                <div class="space-y-2">
                    <Label>Montant livraison</Label>
                    <select
                        v-model="montantLivraisonId"
                        class="w-full rounded-md border border-input bg-background px-3 py-2"
                    >
                        <option value="">—</option>
                        <option
                            v-for="m in montantsLivraison"
                            :key="m.id"
                            :value="m.id"
                        >
                            {{ Number(m.designation).toLocaleString('fr-FR') }}
                            XAF
                        </option>
                    </select>
                </div>
            </div>

            <Button type="submit">Enregistrer les modifications</Button>
        </form>
    </AppLayout>
</template>
