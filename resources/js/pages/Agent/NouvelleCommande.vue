<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

const props = defineProps<{
    pharmacies: Array<{ id: number; designation: string; zone: { designation: string }; adresse: string }>;
    produits: Array<{ id: number; designation: string; dosage?: string; pu: number }>;
    modesPaiement: Array<{ id: number; designation: string }>;
    montantsLivraison: Array<{ id: number; designation: number }>;
    livreurs: Array<{ id: number; nom: string; prenom: string; tel: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dok Board', href: dashboard() },
    { title: 'Agent', href: '/agent' },
    { title: 'Nouvelle commande', href: '#' },
];

const adresseRecherche = ref('');
const pharmacieId = ref<number | ''>('');
const clientId = ref<number | ''>('');
const clientNouveau = ref({ nom: '', prenom: '', tel: '', adresse: '' });
const produitsSelection = ref<Array<{ produit_id: number; quantite: number }>>([]);
const modePaiementId = ref<number | ''>('');
const montantLivraisonId = ref<number | ''>('');
const livreurId = ref<number | ''>('');
const commentaire = ref('');

function ajouterProduit() {
    if (props.produits?.length) {
        produitsSelection.value.push({
            produit_id: props.produits[0].id,
            quantite: 1,
        });
    }
}

function supprimerProduit(i: number) {
    produitsSelection.value.splice(i, 1);
}

onMounted(() => {
    if (!produitsSelection.value.length) ajouterProduit();
});

function submit() {
    const payload: Record<string, unknown> = {
        pharmacie_id: pharmacieId.value || undefined,
        produits: produitsSelection.value.filter((p) => p.produit_id && p.quantite > 0),
        mode_paiement_id: modePaiementId.value || undefined,
        montant_livraison_id: montantLivraisonId.value || undefined,
        livreur_id: livreurId.value || undefined,
        commentaire: commentaire.value || undefined,
    };
    if (clientId.value) {
        payload.client_id = clientId.value;
    } else {
        payload.client_nouveau = clientNouveau.value;
    }
    router.post('/agent/commande', payload);
}
</script>

<template>
    <Head title="Nouvelle commande - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-1 flex-col gap-6 p-4" @submit.prevent="submit">
            <h1 class="text-2xl font-semibold">Nouvelle commande</h1>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-4">
                    <div>
                        <Label>Adresse client (recherche pharmacie proche)</Label>
                        <Input v-model="adresseRecherche" placeholder="Ex: Moungali, Poto-Poto..." class="mt-1" />
                    </div>
                    <div>
                        <Label>Pharmacie *</Label>
                        <select
                            v-model="pharmacieId"
                            required
                            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2"
                        >
                            <option value="">Choisir une pharmacie</option>
                            <option
                                v-for="p in pharmacies"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.designation }} ({{ p.zone?.designation }}) - {{ p.adresse }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <Label>Client existant (ID)</Label>
                        <Input v-model="clientId" type="number" placeholder="Laisser vide pour nouveau client" class="mt-1" />
                    </div>
                    <div v-if="!clientId" class="space-y-2">
                        <Label>Nouveau client</Label>
                        <div class="flex flex-col gap-2">
                            <Input v-model="clientNouveau.nom" placeholder="Nom" />
                            <Input v-model="clientNouveau.prenom" placeholder="Prénom" />
                            <Input v-model="clientNouveau.tel" placeholder="Téléphone" />
                            <Input v-model="clientNouveau.adresse" placeholder="Adresse" />
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <Label>Médicaments *</Label>
                <div class="mt-2 space-y-2">
                    <div
                        v-for="(p, i) in produitsSelection"
                        :key="i"
                        class="flex gap-2"
                    >
                        <select
                            v-model="p.produit_id"
                            class="flex-1 rounded-md border border-input bg-background px-3 py-2"
                        >
                            <option
                                v-for="pr in produits"
                                :key="pr.id"
                                :value="pr.id"
                            >
                                {{ pr.designation }} {{ pr.dosage ?? '' }} - {{ Number(pr.pu).toLocaleString('fr-FR') }} XAF
                            </option>
                        </select>
                        <Input v-model.number="p.quantite" type="number" min="1" class="w-20" />
                        <Button type="button" variant="destructive" @click="supprimerProduit(i)">×</Button>
                    </div>
                    <Button type="button" variant="outline" @click="ajouterProduit">+ Ajouter médicament</Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <Label>Mode de paiement</Label>
                    <select
                        v-model="modePaiementId"
                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2"
                    >
                        <option value="">—</option>
                        <option v-for="m in modesPaiement" :key="m.id" :value="m.id">{{ m.designation }}</option>
                    </select>
                </div>
                <div>
                    <Label>Montant livraison</Label>
                    <select
                        v-model="montantLivraisonId"
                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2"
                    >
                        <option value="">—</option>
                        <option v-for="m in montantsLivraison" :key="m.id" :value="m.id">
                            {{ Number(m.designation).toLocaleString('fr-FR') }} XAF
                        </option>
                    </select>
                </div>
                <div>
                    <Label>Livreur</Label>
                    <select
                        v-model="livreurId"
                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2"
                    >
                        <option value="">—</option>
                        <option v-for="l in livreurs" :key="l.id" :value="l.id">
                            {{ l.prenom }} {{ l.nom }} - {{ l.tel }}
                        </option>
                    </select>
                </div>
            </div>

            <div>
                <Label>Commentaire</Label>
                <Input v-model="commentaire" placeholder="..." class="mt-1" />
            </div>

            <Button type="submit">Créer la commande</Button>
        </form>
    </AppLayout>
</template>
