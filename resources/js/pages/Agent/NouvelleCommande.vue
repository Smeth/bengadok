<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Building2, Phone, Search, Sun, Moon, Shield,
    MapPin, Clock, Plus, X, ChevronLeft, ChevronDown,
    Link2, FileText,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import OrdonnanceFilePreview from '@/components/OrdonnanceFilePreview.vue';
import OrdonnanceUppy from '@/components/OrdonnanceUppy.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Zone { id: number; designation: string }
interface TypePharmacie { id: number; designation: string }
interface Heur { id: number; designation: string }

interface Pharmacie {
    id: number;
    designation: string;
    adresse?: string;
    telephone?: string;
    de_garde: boolean;
    zone?: Zone;
    type_pharmacie?: TypePharmacie;
    heurs?: Heur;
}

interface Medicament {
    designation: string;
    dosage: string;
    forme: string;
    quantite: number;
    prix_unitaire: number;
}

// ─── Props ────────────────────────────────────────────────────────────────────

const props = defineProps<{
    pharmacies: Pharmacie[];
    modesPaiement: Array<{ id: number; designation: string }>;
    livreurs: Array<{ id: number; nom: string; prenom: string; tel: string }>;
}>();

// ─── Breadcrumbs ──────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Agent', href: '/agent' },
    { title: 'Nouvelle commande', href: '#' },
];

// ─── Client ───────────────────────────────────────────────────────────────────

const clientNom = ref('');
const clientPrenom = ref('');
const clientAdresse = ref('');
const clientTel = ref('');
const beneficiaireId = ref<number | ''>('');

// ─── Pharmacie (2 étapes) ─────────────────────────────────────────────────────

const etape = ref<'zone' | 'liste'>('zone');
const zoneSelectionnee = ref<Zone | null>(null);
const pharmacieId = ref<number | null>(null);
const recherchePharmacieTexte = ref('');
const filtreType = ref<'toutes' | 'jour' | 'nuit' | 'garde'>('toutes');

const filtres = [
    { value: 'toutes', label: 'Toutes', icon: Building2 },
    { value: 'jour',   label: 'Jour',   icon: Sun },
    { value: 'nuit',   label: 'Nuit',   icon: Moon },
    { value: 'garde',  label: 'Garde',  icon: Shield },
] as const;

const zonesAvecCompte = computed(() => {
    const map = new Map<number, { zone: Zone; count: number }>();
    for (const p of props.pharmacies) {
        if (!p.zone) continue;
        if (!map.has(p.zone.id)) map.set(p.zone.id, { zone: p.zone, count: 0 });
        map.get(p.zone.id)!.count++;
    }
    return Array.from(map.values());
});

const pharmaciesDeLaZone = computed(() =>
    zoneSelectionnee.value
        ? props.pharmacies.filter(p => p.zone?.id === zoneSelectionnee.value!.id)
        : []
);

const pharmaciesFiltrees = computed(() => {
    let liste = pharmaciesDeLaZone.value;

    if (filtreType.value === 'jour') {
        liste = liste.filter(p => p.type_pharmacie?.designation?.toLowerCase().includes('jour'));
    } else if (filtreType.value === 'nuit') {
        liste = liste.filter(p => p.type_pharmacie?.designation?.toLowerCase().includes('nuit'));
    } else if (filtreType.value === 'garde') {
        liste = liste.filter(p => p.de_garde);
    }

    const q = recherchePharmacieTexte.value.trim().toLowerCase();
    if (q) {
        liste = liste.filter(p =>
            p.designation.toLowerCase().includes(q) ||
            p.adresse?.toLowerCase().includes(q)
        );
    }
    return liste;
});

const pharmacieSelectionnee = computed(() =>
    props.pharmacies.find(p => p.id === pharmacieId.value) ?? null
);

function selectionnerZone(zone: Zone) {
    zoneSelectionnee.value = zone;
    pharmacieId.value = null;
    etape.value = 'liste';
}

function retourZones() {
    etape.value = 'zone';
    zoneSelectionnee.value = null;
    pharmacieId.value = null;
    recherchePharmacieTexte.value = '';
    filtreType.value = 'toutes';
}

// ─── Médicaments ──────────────────────────────────────────────────────────────

const formes = [
    'Comprimé', 'Gélule', 'Sirop', 'Injectable', 'Pommade',
    'Suppositoire', 'Collyre', 'Spray', 'Sachet', 'Ampoule', 'Patch',
];

const medicaments = ref<Medicament[]>([
    { designation: '', dosage: '', forme: '', quantite: 1, prix_unitaire: 0 },
]);

function ajouterMedicament() {
    medicaments.value.push({ designation: '', dosage: '', forme: '', quantite: 1, prix_unitaire: 0 });
}

function supprimerMedicament(i: number) {
    if (medicaments.value.length > 1) medicaments.value.splice(i, 1);
}

function totalLigne(m: Medicament): number {
    return m.quantite * Number(m.prix_unitaire);
}

const totalCommande = computed(() =>
    medicaments.value.reduce((sum, m) => sum + totalLigne(m), 0)
);

// ─── Paiement ─────────────────────────────────────────────────────────────────

const modePaiementId = ref<number | ''>('');

// ─── Ordonnance ───────────────────────────────────────────────────────────────

const ordonnanceFile = ref<File | null>(null);

// ─── Commentaire ──────────────────────────────────────────────────────────────

const commentaire = ref('');

// ─── Soumission ───────────────────────────────────────────────────────────────

const enSubmission = ref(false);

function submit() {
    const produitsValides = medicaments.value
        .filter(m => m.designation.trim() && m.quantite > 0)
        .map(m => ({
            designation: m.designation.trim(),
            dosage: m.dosage.trim() || null,
            forme: m.forme || null,
            quantite: m.quantite,
            prix_unitaire: Number(m.prix_unitaire),
        }));

    if (!produitsValides.length || !pharmacieId.value) return;

    enSubmission.value = true;

    const clientNouveau = {
        nom: clientNom.value,
        prenom: clientPrenom.value,
        tel: clientTel.value,
        adresse: clientAdresse.value,
    };

    if (ordonnanceFile.value) {
        const formData = new FormData();
        formData.append('pharmacie_id', String(pharmacieId.value));
        formData.append('produits', JSON.stringify(produitsValides));
        formData.append('ordonnance', ordonnanceFile.value);
        formData.append('client_nouveau', JSON.stringify(clientNouveau));
        if (modePaiementId.value) formData.append('mode_paiement_id', String(modePaiementId.value));
        if (beneficiaireId.value) formData.append('livreur_id', String(beneficiaireId.value));
        if (commentaire.value) formData.append('commentaire', commentaire.value);
        router.post('/agent/commande', formData, {
            forceFormData: true,
            onFinish: () => { enSubmission.value = false; },
        });
    } else {
        router.post('/agent/commande', {
            pharmacie_id: pharmacieId.value,
            produits: produitsValides,
            client_nouveau: clientNouveau,
            mode_paiement_id: modePaiementId.value || undefined,
            livreur_id: beneficiaireId.value || undefined,
            commentaire: commentaire.value || undefined,
        }, {
            onFinish: () => { enSubmission.value = false; },
        });
    }
}

function annuler() {
    router.visit('/agent');
}
</script>

<template>
    <Head title="Nouvelle commande - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Backdrop -->
        <div class="flex flex-1 items-start justify-center p-4"
             style="background: linear-gradient(135deg, rgba(91,176,52,0.12) 0%, rgba(52,176,199,0.12) 100%);">

            <!-- Modal card -->
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl my-2">

                <!-- ── En-tête ── -->
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <div class="flex items-center gap-2">
                        <FileText class="h-5 w-5 text-blue-600" />
                        <h1 class="font-mono text-lg font-semibold tracking-wide">Enregistrement Commande</h1>
                    </div>
                    <button @click="annuler" class="text-red-500 hover:text-red-700 transition-colors">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- ── Corps (scrollable) ── -->
                <div class="max-h-[75vh] overflow-y-auto px-6 py-5 space-y-5">

                    <!-- ── Section Client ── -->

                    <!-- Ligne 1 : Nom | Prénom | Adresse -->
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Nom du Client</label>
                            <input
                                v-model="clientNom"
                                placeholder="Ex : Fofana Didier"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Prénom du Client</label>
                            <input
                                v-model="clientPrenom"
                                placeholder="Ex : Fofana Didier"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Adresse</label>
                            <input
                                v-model="clientAdresse"
                                placeholder="Ex : 20 rue Loby Moungali"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>

                    <!-- Ligne 2 : Bénéficiaire | Téléphone -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Bénéficiaire</label>
                            <div class="relative mt-1">
                                <select
                                    v-model="beneficiaireId"
                                    class="w-full appearance-none rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">choisir un bénéficiaire</option>
                                    <option v-for="l in livreurs" :key="l.id" :value="l.id">
                                        {{ l.prenom }} {{ l.nom }}
                                    </option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-2.5 h-4 w-4 text-gray-400" />
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Téléphone</label>
                            <div class="relative mt-1">
                                <Phone class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                <input
                                    v-model="clientTel"
                                    placeholder="+242 06 800 8008"
                                    class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- ── Section Pharmacie Partenaire ── -->
                    <div class="rounded-xl border border-gray-200 p-4">
                        <h2 class="mb-3 text-sm font-semibold text-gray-400">Pharmacie Partenaire</h2>

                        <!-- Étape 1 : sélection de zone -->
                        <template v-if="etape === 'zone'">
                            <p class="mb-3 text-sm text-gray-600">Sélectionner d'abord une zone de Brazzaville</p>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <button
                                    v-for="{ zone, count } in zonesAvecCompte"
                                    :key="zone.id"
                                    type="button"
                                    @click="selectionnerZone(zone)"
                                    class="flex flex-col items-center gap-1.5 rounded-xl border border-gray-200 p-3 transition-colors hover:border-blue-400 hover:bg-blue-50 cursor-pointer"
                                >
                                    <Building2 class="h-8 w-8 text-gray-500" />
                                    <span class="text-sm font-semibold text-gray-800">{{ zone.designation }}</span>
                                    <span class="text-xs text-gray-400">{{ count }} Pharmacies</span>
                                </button>
                            </div>
                        </template>

                        <!-- Étape 2 : liste des pharmacies -->
                        <template v-else>
                            <div class="mb-3 flex items-center gap-3">
                                <button
                                    type="button"
                                    @click="retourZones"
                                    class="flex items-center gap-1 rounded-full border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-100 transition-colors"
                                >
                                    <ChevronLeft class="h-3 w-3" /> Retour
                                </button>
                                <span class="text-sm font-medium text-gray-700">Sélectionner une pharmacie</span>
                            </div>

                            <!-- Barre recherche + filtres -->
                            <div class="mb-3 flex flex-wrap gap-2">
                                <div class="relative min-w-[180px] flex-1">
                                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                    <input
                                        v-model="recherchePharmacieTexte"
                                        placeholder="Recherche une pharmacie"
                                        class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                                    />
                                </div>
                                <div class="flex gap-1.5">
                                    <button
                                        v-for="f in filtres"
                                        :key="f.value"
                                        type="button"
                                        @click="filtreType = f.value"
                                        :class="[
                                            'flex items-center gap-1 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                                            filtreType === f.value
                                                ? 'border-blue-500 bg-blue-50 text-blue-700'
                                                : 'border-gray-300 text-gray-600 hover:bg-gray-50',
                                        ]"
                                    >
                                        <component :is="f.icon" class="h-3 w-3" />
                                        {{ f.label }}
                                    </button>
                                </div>
                            </div>

                            <!-- Cartes pharmacies -->
                            <div class="max-h-44 space-y-2 overflow-y-auto pr-1">
                                <div
                                    v-for="p in pharmaciesFiltrees"
                                    :key="p.id"
                                    @click="pharmacieId = p.id"
                                    :class="[
                                        'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors',
                                        pharmacieId === p.id
                                            ? 'border-green-500 bg-green-50'
                                            : 'border-gray-200 hover:border-green-300 hover:bg-green-50/40',
                                    ]"
                                >
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600">
                                        <Plus class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-gray-800">{{ p.designation }}</p>
                                        <p v-if="p.adresse" class="mt-0.5 flex items-center gap-1 text-xs text-gray-500">
                                            <MapPin class="h-3 w-3 shrink-0" />
                                            <span class="truncate">{{ p.adresse }}</span>
                                        </p>
                                        <p v-if="p.telephone" class="flex items-center gap-1 text-xs text-gray-500">
                                            <Phone class="h-3 w-3 shrink-0" /> {{ p.telephone }}
                                        </p>
                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <span v-if="p.heurs" class="flex items-center gap-1 text-xs text-gray-400">
                                                <Clock class="h-3 w-3" /> {{ p.heurs.designation }}
                                            </span>
                                            <span v-if="p.de_garde" class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">
                                                Garde
                                            </span>
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                                Ouvert
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="!pharmaciesFiltrees.length" class="py-4 text-center text-sm text-gray-400">
                                    Aucune pharmacie trouvée
                                </p>
                            </div>
                        </template>
                    </div>

                    <!-- ── Section Médicaments ── -->
                    <div class="rounded-xl border border-gray-200 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-400">Médicaments</h2>
                            <button
                                type="button"
                                @click="ajouterMedicament"
                                class="flex items-center gap-2 rounded-full bg-blue-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors"
                            >
                                <Link2 class="h-3 w-3" /> Ajouter un médicament
                            </button>
                        </div>

                        <!-- En-têtes colonnes -->
                        <div class="mb-2 grid grid-cols-4 gap-2 text-xs font-medium text-gray-600">
                            <span>Nom Médicament</span>
                            <span>Dosage</span>
                            <span>Forme</span>
                            <span>Quantité</span>
                        </div>

                        <!-- Lignes -->
                        <div v-for="(m, i) in medicaments" :key="i" class="mb-4 last:mb-0">
                            <!-- Ligne 1 : désignation / dosage / forme / quantité -->
                            <div class="mb-2 grid grid-cols-4 gap-2">
                                <input
                                    v-model="m.designation"
                                    placeholder="Ex : 1000"
                                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                                />
                                <input
                                    v-model="m.dosage"
                                    placeholder="Ex : 1000"
                                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                                />
                                <div class="relative">
                                    <select
                                        v-model="m.forme"
                                        class="w-full appearance-none rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                                    >
                                        <option value="">Choisir la forme</option>
                                        <option v-for="f in formes" :key="f" :value="f">{{ f }}</option>
                                    </select>
                                    <ChevronDown class="pointer-events-none absolute right-2 top-2.5 h-4 w-4 text-gray-400" />
                                </div>
                                <input
                                    v-model.number="m.quantite"
                                    type="number"
                                    min="1"
                                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                                />
                            </div>

                            <!-- Ligne 2 : prix unitaire / total -->
                            <div class="grid grid-cols-4 gap-2 items-end">
                                <div>
                                    <span class="mb-1 block text-xs text-gray-500">Prix unitaire</span>
                                    <div class="flex items-center gap-1">
                                        <input
                                            v-model.number="m.prix_unitaire"
                                            type="number"
                                            min="0"
                                            placeholder="Ex : 1000"
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                                        />
                                        <span class="shrink-0 text-xs text-gray-400">xaf</span>
                                    </div>
                                </div>
                                <div>
                                    <span class="mb-1 block text-xs text-gray-500">Total</span>
                                    <div class="flex items-center gap-1">
                                        <input
                                            :value="totalLigne(m).toFixed(0)"
                                            readonly
                                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600"
                                        />
                                        <span class="shrink-0 text-xs text-gray-400">xaf</span>
                                    </div>
                                </div>
                                <div class="col-span-2 flex justify-end">
                                    <button
                                        v-if="medicaments.length > 1"
                                        type="button"
                                        @click="supprimerMedicament(i)"
                                        class="text-xs text-red-400 hover:text-red-600 transition-colors"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Total commande -->
                        <div class="mt-3 flex items-baseline gap-2 border-t pt-3">
                            <span class="text-sm font-semibold text-gray-700">Total montant commande :</span>
                            <span class="text-2xl font-bold text-gray-900">{{ totalCommande.toFixed(1) }}</span>
                            <span class="text-sm text-gray-500">xaf</span>
                        </div>
                    </div>

                    <!-- ── Section Informations paiement ── -->
                    <div class="rounded-xl border border-gray-200 p-4">
                        <h2 class="mb-3 text-sm font-semibold text-gray-400">Informations paiement</h2>
                        <div class="flex items-center gap-4">
                            <label class="shrink-0 text-sm font-medium text-gray-700">Mode de paiement</label>
                            <div class="relative flex-1">
                                <select
                                    v-model="modePaiementId"
                                    class="w-full appearance-none rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">choisir le mode de paiement</option>
                                    <option v-for="m in modesPaiement" :key="m.id" :value="m.id">
                                        {{ m.designation }}
                                    </option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-2.5 h-4 w-4 text-gray-400" />
                            </div>
                        </div>
                    </div>

                    <!-- ── Ordonnance + Commentaires ── -->
                    <div class="grid grid-cols-2 gap-4">

                        <!-- Ordonnance (FilePond) -->
                        <div class="flex flex-col gap-2">
                            <OrdonnanceUppy v-model="ordonnanceFile" label="Ordonnance" />
                            <OrdonnanceFilePreview v-if="ordonnanceFile" :file="ordonnanceFile" max-height="12rem" />
                        </div>

                        <!-- Commentaires -->
                        <textarea
                            v-model="commentaire"
                            placeholder="Commentaires ..."
                            rows="5"
                            class="w-full resize-none rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-400"
                        />
                    </div>

                </div>
                <!-- ── Fin du corps ── -->

                <!-- ── Pied de page ── -->
                <div class="flex items-center justify-center gap-4 border-t px-6 py-4">
                    <button
                        type="button"
                        @click="annuler"
                        class="rounded-full bg-red-500 px-10 py-2.5 font-medium text-white transition-colors hover:bg-red-600"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        @click="submit"
                        :disabled="enSubmission"
                        class="rounded-full bg-blue-600 px-10 py-2.5 font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-60"
                    >
                        {{ enSubmission ? 'Envoi...' : 'Envoyer' }}
                    </button>
                </div>

            </div>
            <!-- ── Fin modal card ── -->

        </div>
    </AppLayout>
</template>
