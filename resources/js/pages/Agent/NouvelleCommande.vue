<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

const props = defineProps<{
    pharmacies: Array<{ id: number; designation: string; zone?: { designation: string }; adresse?: string }>;
    modesPaiement: Array<{ id: number; designation: string }>;
    montantsLivraison: Array<{ id: number; designation: number }>;
    livreurs: Array<{ id: number; nom: string; prenom: string; tel: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Agent', href: '/agent' },
    { title: 'Nouvelle commande', href: '#' },
];

const adresseRecherche = ref('');
const clientNouveau = ref({ nom: '', prenom: '', tel: '', adresse: '' });
const pharmaciesProches = ref<typeof props.pharmacies>([]);
const pharmacieId = ref<number | ''>('');

const pharmaciesListe = computed(() =>
    pharmaciesProches.value.length ? pharmaciesProches.value : props.pharmacies
);

watch([adresseRecherche, () => clientNouveau.value.adresse], async ([addr1, addr2]) => {
    const addr = (addr1 as string) || (addr2 as string) || '';
    if (addr.length >= 2) {
        try {
            const { data } = await axios.get('/agent/recherche-pharmacie', { params: { adresse: addr } });
            pharmaciesProches.value = data.pharmacies || [];
            if (!pharmacieId.value && data.pharmacies?.length) {
                pharmacieId.value = data.pharmacies[0].id;
            }
        } catch {
            pharmaciesProches.value = [];
        }
    } else {
        pharmaciesProches.value = [];
    }
});
const clientId = ref<number | ''>('');
const produitsSelection = ref<Array<{ designation: string; dosage: string; quantite: number; prix_unitaire: number }>>([]);
const modePaiementId = ref<number | ''>('');
const montantLivraisonId = ref<number | ''>('');
const livreurId = ref<number | ''>('');
const commentaire = ref('');
const ordonnanceFile = ref<File | null>(null);

function ajouterProduit() {
    produitsSelection.value.push({ designation: '', dosage: '', quantite: 1, prix_unitaire: 0 });
}

function supprimerProduit(i: number) {
    produitsSelection.value.splice(i, 1);
}

function onOrdonnanceChange(e: Event) {
    const target = e.target as HTMLInputElement;
    ordonnanceFile.value = target.files?.[0] ?? null;
}

onMounted(() => {
    if (!produitsSelection.value.length) ajouterProduit();
});

function submit() {
    const produitsValides = produitsSelection.value
        .filter((p) => p.designation.trim() && p.quantite > 0 && Number(p.prix_unitaire) >= 0)
        .map((p) => ({
            designation: p.designation.trim(),
            dosage: (p.dosage ?? '').trim() || null,
            quantite: p.quantite,
            prix_unitaire: Number(p.prix_unitaire),
        }));
    if (!produitsValides.length) return;

    const payload: Record<string, unknown> = {
        pharmacie_id: pharmacieId.value || undefined,
        produits: produitsValides,
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

    if (ordonnanceFile.value) {
        const formData = new FormData();
        formData.append('pharmacie_id', String(payload.pharmacie_id));
        formData.append('produits', JSON.stringify(produitsValides));
        formData.append('ordonnance', ordonnanceFile.value);
        if (payload.client_id) formData.append('client_id', String(payload.client_id));
        else formData.append('client_nouveau', JSON.stringify(payload.client_nouveau));
        if (payload.mode_paiement_id) formData.append('mode_paiement_id', String(payload.mode_paiement_id));
        if (payload.montant_livraison_id) formData.append('montant_livraison_id', String(payload.montant_livraison_id));
        if (payload.livreur_id) formData.append('livreur_id', String(payload.livreur_id));
        if (payload.commentaire) formData.append('commentaire', String(payload.commentaire));
        router.post('/agent/commande', formData, { forceFormData: true });
    } else {
        router.post('/agent/commande', payload);
    }
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
                                v-for="(p, idx) in pharmaciesListe"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ pharmaciesProches.length ? (idx === 0 ? '1ère proche - ' : `${idx + 1}ème proche - `) : '' }}{{ p.designation }} ({{ p.zone?.designation }}) - {{ p.adresse }}
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
                <Label>Médicaments * (créés pendant la commande)</Label>
                <div class="mt-2 space-y-2">
                    <div
                        v-for="(p, i) in produitsSelection"
                        :key="i"
                        class="flex flex-wrap items-end gap-2"
                    >
                        <Input v-model="p.designation" placeholder="Désignation (ex: Doliprane)" class="min-w-[120px] flex-1" />
                        <Input v-model="p.dosage" placeholder="Dosage (ex: 500mg)" class="w-24" />
                        <Input v-model.number="p.quantite" type="number" min="1" class="w-16" placeholder="Qté" />
                        <Input v-model.number="p.prix_unitaire" type="number" min="0" class="w-24" placeholder="Prix" />
                        <Button type="button" variant="destructive" size="icon" @click="supprimerProduit(i)">×</Button>
                    </div>
                    <Button type="button" variant="outline" @click="ajouterProduit">+ Ajouter médicament</Button>
                </div>
            </div>

            <div>
                <Label>Ordonnance (image ou PDF)</Label>
                <Input
                    type="file"
                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                    class="mt-1 cursor-pointer"
                    @change="onOrdonnanceChange"
                />
                <p class="mt-1 text-xs text-muted-foreground">JPG, PNG, GIF, WebP ou PDF. Max 10 Mo.</p>
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
