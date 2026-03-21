<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import { Plus, Pencil, Trash2, Check, X, MapPin, CreditCard, Truck, User, Clock, Building2, RefreshCw } from 'lucide-vue-next';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Zone          { id: number; designation: string; latitude?: number; longitude?: number; pharmacies_count: number }
interface ModePaiement  { id: number; designation: string; commandes_count: number }
interface MontantLivraison { id: number; designation: number; commandes_count: number }
interface Livreur       { id: number; nom: string; prenom: string; tel: string; commandes_count: number }
interface Heur          { id: number; ouverture: string; fermeture: string; pharmacies_count: number }
interface TypePharmacie { id: number; designation: string; heurs_id?: number; heurs?: Heur; pharmacies_count: number }
interface MotifAnnulationRow {
    id: number;
    slug: string;
    label: string;
    autorise_relance: boolean;
    sort_order: number;
    commandes_count: number;
}

withDefaults(
    defineProps<{
        zones:               Zone[];
        modesPaiement:       ModePaiement[];
        montantsLivraison:   MontantLivraison[];
        livreurs:            Livreur[];
        heurs:               Heur[];
        typesPharmacie:      TypePharmacie[];
        motifsAnnulation:    MotifAnnulationRow[];
    }>(),
    {
        motifsAnnulation: () => [],
    }
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Configuration', href: '/settings/parametres' },
];

// ─── Onglet actif ─────────────────────────────────────────────────────────────

const onglets = [
    { id: 'zones',              label: 'Zones',              icon: MapPin },
    { id: 'modesPaiement',     label: 'Modes de paiement',  icon: CreditCard },
    { id: 'montantsLivraison', label: 'Montants livraison',  icon: Truck },
    { id: 'livreurs',          label: 'Livreurs',            icon: User },
    { id: 'horaires',          label: 'Horaires',            icon: Clock },
    { id: 'typesPharmacie',    label: 'Types pharmacie',     icon: Building2 },
    { id: 'motifsAnnulation',  label: 'Motifs d\'annulation', icon: RefreshCw },
] as const;

type OngletId = typeof onglets[number]['id'];
const ongletActif = ref<OngletId>('zones');

// ─── Utilitaires formulaire ───────────────────────────────────────────────────

const enCours = ref(false);
const editingId = ref<number | null>(null);

function startEdit(id: number) { editingId.value = id; }
function cancelEdit()          { editingId.value = null; }

function submit(url: string, data: Record<string, unknown>, method: 'post' | 'patch' | 'delete' = 'post') {
    enCours.value = true;
    router[method](url, data, {
        onSuccess: () => { editingId.value = null; },
        onFinish:  () => { enCours.value = false; },
    });
}

// ─── Zones ────────────────────────────────────────────────────────────────────

const zoneForm    = ref({ designation: '', latitude: '', longitude: '' });
const zoneEdit    = ref({ designation: '', latitude: '', longitude: '' });

function ajouterZone() {
    submit('/settings/parametres/zones', zoneForm.value);
    zoneForm.value = { designation: '', latitude: '', longitude: '' };
}
function ouvrirEditZone(z: Zone) {
    zoneEdit.value = { designation: z.designation, latitude: String(z.latitude ?? ''), longitude: String(z.longitude ?? '') };
    startEdit(z.id);
}
function sauvegarderZone(id: number) {
    submit(`/settings/parametres/zones/${id}`, zoneEdit.value, 'patch');
}
function supprimerZone(id: number) {
    if (confirm('Supprimer cette zone ?')) submit(`/settings/parametres/zones/${id}`, {}, 'delete');
}

// ─── Modes de paiement ────────────────────────────────────────────────────────

const modeForm = ref({ designation: '' });
const modeEdit = ref({ designation: '' });

function ajouterMode() {
    submit('/settings/parametres/modes-paiement', modeForm.value);
    modeForm.value = { designation: '' };
}
function ouvrirEditMode(m: ModePaiement) {
    modeEdit.value = { designation: m.designation };
    startEdit(m.id);
}
function sauvegarderMode(id: number) {
    submit(`/settings/parametres/modes-paiement/${id}`, modeEdit.value, 'patch');
}
function supprimerMode(id: number) {
    if (confirm('Supprimer ce mode de paiement ?')) submit(`/settings/parametres/modes-paiement/${id}`, {}, 'delete');
}

// ─── Montants de livraison ────────────────────────────────────────────────────

const montantForm = ref({ designation: '' });
const montantEdit = ref({ designation: '' });

function ajouterMontant() {
    submit('/settings/parametres/montants-livraison', montantForm.value);
    montantForm.value = { designation: '' };
}
function ouvrirEditMontant(m: MontantLivraison) {
    montantEdit.value = { designation: String(m.designation) };
    startEdit(m.id);
}
function sauvegarderMontant(id: number) {
    submit(`/settings/parametres/montants-livraison/${id}`, montantEdit.value, 'patch');
}
function supprimerMontant(id: number) {
    if (confirm('Supprimer ce montant de livraison ?')) submit(`/settings/parametres/montants-livraison/${id}`, {}, 'delete');
}

// ─── Livreurs ─────────────────────────────────────────────────────────────────

const livreurForm = ref({ nom: '', prenom: '', tel: '' });
const livreurEdit = ref({ nom: '', prenom: '', tel: '' });

function ajouterLivreur() {
    submit('/settings/parametres/livreurs', livreurForm.value);
    livreurForm.value = { nom: '', prenom: '', tel: '' };
}
function ouvrirEditLivreur(l: Livreur) {
    livreurEdit.value = { nom: l.nom, prenom: l.prenom, tel: l.tel };
    startEdit(l.id);
}
function sauvegarderLivreur(id: number) {
    submit(`/settings/parametres/livreurs/${id}`, livreurEdit.value, 'patch');
}
function supprimerLivreur(id: number) {
    if (confirm('Supprimer ce livreur ?')) submit(`/settings/parametres/livreurs/${id}`, {}, 'delete');
}

// ─── Horaires ─────────────────────────────────────────────────────────────────

const heurForm = ref({ ouverture: '', fermeture: '' });
const heurEdit = ref({ ouverture: '', fermeture: '' });

function ajouterHeur() {
    submit('/settings/parametres/heurs', heurForm.value);
    heurForm.value = { ouverture: '', fermeture: '' };
}
function ouvrirEditHeur(h: Heur) {
    heurEdit.value = { ouverture: h.ouverture, fermeture: h.fermeture };
    startEdit(h.id);
}
function sauvegarderHeur(id: number) {
    submit(`/settings/parametres/heurs/${id}`, heurEdit.value, 'patch');
}
function supprimerHeur(id: number) {
    if (confirm('Supprimer cet horaire ?')) submit(`/settings/parametres/heurs/${id}`, {}, 'delete');
}

// ─── Types de pharmacie ───────────────────────────────────────────────────────

const typeForm = ref({ designation: '', heurs_id: '' });
const typeEdit = ref({ designation: '', heurs_id: '' });

function ajouterType() {
    submit('/settings/parametres/types-pharmacie', typeForm.value);
    typeForm.value = { designation: '', heurs_id: '' };
}
function ouvrirEditType(t: TypePharmacie) {
    typeEdit.value = { designation: t.designation, heurs_id: String(t.heurs_id ?? '') };
    startEdit(t.id);
}
function sauvegarderType(id: number) {
    submit(`/settings/parametres/types-pharmacie/${id}`, typeEdit.value, 'patch');
}
function supprimerType(id: number) {
    if (confirm('Supprimer ce type de pharmacie ?')) submit(`/settings/parametres/types-pharmacie/${id}`, {}, 'delete');
}

// ─── Motifs d'annulation ─────────────────────────────────────────────────────

const motifForm = ref({ slug: '', label: '', sort_order: '', autorise_relance: false });
const motifEdit = ref({ slug: '', label: '', sort_order: '', autorise_relance: false });

function ajouterMotif() {
    const data: Record<string, unknown> = {
        slug: motifForm.value.slug.trim(),
        label: motifForm.value.label.trim(),
        autorise_relance: motifForm.value.autorise_relance,
    };
    if (motifForm.value.sort_order !== '') {
        data.sort_order = Number(motifForm.value.sort_order);
    }
    submit('/settings/parametres/motifs-annulation', data);
    motifForm.value = { slug: '', label: '', sort_order: '', autorise_relance: false };
}

function ouvrirEditMotif(m: MotifAnnulationRow) {
    motifEdit.value = {
        slug: m.slug,
        label: m.label,
        sort_order: String(m.sort_order ?? 0),
        autorise_relance: m.autorise_relance,
    };
    startEdit(m.id);
}

function sauvegarderMotif(id: number) {
    submit(`/settings/parametres/motifs-annulation/${id}`, {
        slug: motifEdit.value.slug.trim(),
        label: motifEdit.value.label.trim(),
        autorise_relance: motifEdit.value.autorise_relance,
        sort_order: motifEdit.value.sort_order === '' ? undefined : Number(motifEdit.value.sort_order),
    }, 'patch');
}

function supprimerMotif(id: number) {
    if (confirm('Supprimer ce motif ? Impossible s’il est utilisé par des commandes.')) {
        submit(`/settings/parametres/motifs-annulation/${id}`, {}, 'delete');
    }
}
</script>

<template>
    <Head title="Configuration — BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-5xl p-6 space-y-6">

            <!-- En-tête page -->
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Configuration</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Configurez les données de référence utilisées dans l'application.
                </p>
            </div>

            <!-- Onglets -->
            <div class="flex flex-wrap gap-1 rounded-xl bg-gray-100 p-1">
                <button
                    v-for="o in onglets"
                    :key="o.id"
                    type="button"
                    @click="ongletActif = o.id; editingId = null"
                    :class="[
                        'flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                        ongletActif === o.id
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700',
                    ]"
                >
                    <component :is="o.icon" class="h-3.5 w-3.5" />
                    {{ o.label }}
                </button>
            </div>

            <!-- ══════════════════ ZONES ══════════════════ -->
            <section v-if="ongletActif === 'zones'" class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <div class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                        <MapPin class="h-4 w-4 text-green-600" /> Zones de Brazzaville
                    </h2>
                    <span class="text-xs text-gray-400">{{ zones.length }} zone(s)</span>
                </div>

                <!-- Formulaire ajout -->
                <form @submit.prevent="ajouterZone" class="border-b px-5 py-4 bg-green-50/40">
                    <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">Nouvelle zone</p>
                    <div class="grid grid-cols-3 gap-3">
                        <input v-model="zoneForm.designation" placeholder="Nom de la zone *" required
                            class="col-span-3 sm:col-span-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400" />
                        <input v-model="zoneForm.latitude" type="number" step="any" placeholder="Latitude (optionnel)"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400" />
                        <input v-model="zoneForm.longitude" type="number" step="any" placeholder="Longitude (optionnel)"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400" />
                    </div>
                    <button type="submit" :disabled="enCours"
                        class="mt-3 flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-60">
                        <Plus class="h-4 w-4" /> Ajouter
                    </button>
                </form>

                <!-- Liste -->
                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">Désignation</th>
                            <th class="px-5 py-3 text-left font-medium">Coordonnées</th>
                            <th class="px-5 py-3 text-left font-medium">Pharmacies</th>
                            <th class="px-5 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!zones.length">
                            <td colspan="4" class="px-5 py-6 text-center text-gray-400">Aucune zone enregistrée.</td>
                        </tr>
                        <tr v-for="z in zones" :key="z.id" class="hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                <input v-if="editingId === z.id" v-model="zoneEdit.designation"
                                    class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                <span v-else class="font-medium text-gray-800">{{ z.designation }}</span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">
                                <div v-if="editingId === z.id" class="flex gap-2">
                                    <input v-model="zoneEdit.latitude" placeholder="Lat" type="number" step="any"
                                        class="w-24 rounded border border-gray-300 px-2 py-1 text-sm" />
                                    <input v-model="zoneEdit.longitude" placeholder="Lng" type="number" step="any"
                                        class="w-24 rounded border border-gray-300 px-2 py-1 text-sm" />
                                </div>
                                <span v-else>
                                    {{ z.latitude ? `${z.latitude}, ${z.longitude}` : '—' }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                    {{ z.pharmacies_count }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === z.id">
                                    <button @click="sauvegarderZone(z.id)" :disabled="enCours"
                                        class="mr-2 text-green-600 hover:text-green-800"><Check class="h-4 w-4" /></button>
                                    <button @click="cancelEdit" class="text-gray-400 hover:text-gray-600"><X class="h-4 w-4" /></button>
                                </template>
                                <template v-else>
                                    <button @click="ouvrirEditZone(z)" class="mr-3 text-blue-500 hover:text-blue-700"><Pencil class="h-4 w-4" /></button>
                                    <button @click="supprimerZone(z.id)" class="text-red-400 hover:text-red-600"><Trash2 class="h-4 w-4" /></button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ MODES DE PAIEMENT ══════════════════ -->
            <section v-if="ongletActif === 'modesPaiement'" class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <div class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                        <CreditCard class="h-4 w-4 text-blue-600" /> Modes de paiement
                    </h2>
                    <span class="text-xs text-gray-400">{{ modesPaiement.length }} mode(s)</span>
                </div>

                <form @submit.prevent="ajouterMode" class="border-b px-5 py-4 bg-blue-50/40">
                    <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">Nouveau mode</p>
                    <div class="flex gap-3">
                        <input v-model="modeForm.designation" placeholder="Ex : Mobile Money, Espèces..." required
                            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
                        <button type="submit" :disabled="enCours"
                            class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                            <Plus class="h-4 w-4" /> Ajouter
                        </button>
                    </div>
                </form>

                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">Désignation</th>
                            <th class="px-5 py-3 text-left font-medium">Commandes liées</th>
                            <th class="px-5 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!modesPaiement.length">
                            <td colspan="3" class="px-5 py-6 text-center text-gray-400">Aucun mode de paiement.</td>
                        </tr>
                        <tr v-for="m in modesPaiement" :key="m.id" class="hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                <input v-if="editingId === m.id" v-model="modeEdit.designation"
                                    class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                <span v-else class="font-medium text-gray-800">{{ m.designation }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ m.commandes_count }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === m.id">
                                    <button @click="sauvegarderMode(m.id)" :disabled="enCours" class="mr-2 text-green-600 hover:text-green-800"><Check class="h-4 w-4" /></button>
                                    <button @click="cancelEdit" class="text-gray-400 hover:text-gray-600"><X class="h-4 w-4" /></button>
                                </template>
                                <template v-else>
                                    <button @click="ouvrirEditMode(m)" class="mr-3 text-blue-500 hover:text-blue-700"><Pencil class="h-4 w-4" /></button>
                                    <button @click="supprimerMode(m.id)" class="text-red-400 hover:text-red-600"><Trash2 class="h-4 w-4" /></button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ MONTANTS LIVRAISON ══════════════════ -->
            <section v-if="ongletActif === 'montantsLivraison'" class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <div class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                        <Truck class="h-4 w-4 text-orange-600" /> Montants de livraison
                    </h2>
                    <span class="text-xs text-gray-400">{{ montantsLivraison.length }} tarif(s)</span>
                </div>

                <form @submit.prevent="ajouterMontant" class="border-b px-5 py-4 bg-orange-50/40">
                    <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">Nouveau tarif</p>
                    <div class="flex gap-3 items-center">
                        <input v-model="montantForm.designation" type="number" min="0" step="0.01" placeholder="Montant en FCFA *" required
                            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
                        <span class="text-sm text-gray-500 font-medium">FCFA</span>
                        <button type="submit" :disabled="enCours"
                            class="flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600 disabled:opacity-60">
                            <Plus class="h-4 w-4" /> Ajouter
                        </button>
                    </div>
                </form>

                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">Montant (FCFA)</th>
                            <th class="px-5 py-3 text-left font-medium">Commandes liées</th>
                            <th class="px-5 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!montantsLivraison.length">
                            <td colspan="3" class="px-5 py-6 text-center text-gray-400">Aucun tarif de livraison.</td>
                        </tr>
                        <tr v-for="m in montantsLivraison" :key="m.id" class="hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                <div v-if="editingId === m.id" class="flex items-center gap-2">
                                    <input v-model="montantEdit.designation" type="number" min="0" step="0.01"
                                        class="w-32 rounded border border-gray-300 px-2 py-1 text-sm" />
                                    <span class="text-xs text-gray-400">FCFA</span>
                                </div>
                                <span v-else class="font-medium text-gray-800">
                                    {{ Number(m.designation).toLocaleString('fr-FR') }} FCFA
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ m.commandes_count }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === m.id">
                                    <button @click="sauvegarderMontant(m.id)" :disabled="enCours" class="mr-2 text-green-600 hover:text-green-800"><Check class="h-4 w-4" /></button>
                                    <button @click="cancelEdit" class="text-gray-400 hover:text-gray-600"><X class="h-4 w-4" /></button>
                                </template>
                                <template v-else>
                                    <button @click="ouvrirEditMontant(m)" class="mr-3 text-blue-500 hover:text-blue-700"><Pencil class="h-4 w-4" /></button>
                                    <button @click="supprimerMontant(m.id)" class="text-red-400 hover:text-red-600"><Trash2 class="h-4 w-4" /></button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ LIVREURS ══════════════════ -->
            <section v-if="ongletActif === 'livreurs'" class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <div class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                        <User class="h-4 w-4 text-purple-600" /> Livreurs
                    </h2>
                    <span class="text-xs text-gray-400">{{ livreurs.length }} livreur(s)</span>
                </div>

                <form @submit.prevent="ajouterLivreur" class="border-b px-5 py-4 bg-purple-50/40">
                    <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">Nouveau livreur</p>
                    <div class="grid grid-cols-3 gap-3">
                        <input v-model="livreurForm.prenom" placeholder="Prénom *" required
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400" />
                        <input v-model="livreurForm.nom" placeholder="Nom *" required
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400" />
                        <input v-model="livreurForm.tel" placeholder="Téléphone *" required
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400" />
                    </div>
                    <button type="submit" :disabled="enCours"
                        class="mt-3 flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-60">
                        <Plus class="h-4 w-4" /> Ajouter
                    </button>
                </form>

                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">Prénom & Nom</th>
                            <th class="px-5 py-3 text-left font-medium">Téléphone</th>
                            <th class="px-5 py-3 text-left font-medium">Commandes</th>
                            <th class="px-5 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!livreurs.length">
                            <td colspan="4" class="px-5 py-6 text-center text-gray-400">Aucun livreur enregistré.</td>
                        </tr>
                        <tr v-for="l in livreurs" :key="l.id" class="hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                <div v-if="editingId === l.id" class="flex gap-2">
                                    <input v-model="livreurEdit.prenom" placeholder="Prénom"
                                        class="w-28 rounded border border-gray-300 px-2 py-1 text-sm" />
                                    <input v-model="livreurEdit.nom" placeholder="Nom"
                                        class="w-28 rounded border border-gray-300 px-2 py-1 text-sm" />
                                </div>
                                <span v-else class="font-medium text-gray-800">{{ l.prenom }} {{ l.nom }}</span>
                            </td>
                            <td class="px-5 py-3 text-gray-600">
                                <input v-if="editingId === l.id" v-model="livreurEdit.tel"
                                    class="w-32 rounded border border-gray-300 px-2 py-1 text-sm" />
                                <span v-else>{{ l.tel }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ l.commandes_count }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === l.id">
                                    <button @click="sauvegarderLivreur(l.id)" :disabled="enCours" class="mr-2 text-green-600 hover:text-green-800"><Check class="h-4 w-4" /></button>
                                    <button @click="cancelEdit" class="text-gray-400 hover:text-gray-600"><X class="h-4 w-4" /></button>
                                </template>
                                <template v-else>
                                    <button @click="ouvrirEditLivreur(l)" class="mr-3 text-blue-500 hover:text-blue-700"><Pencil class="h-4 w-4" /></button>
                                    <button @click="supprimerLivreur(l.id)" class="text-red-400 hover:text-red-600"><Trash2 class="h-4 w-4" /></button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ HORAIRES ══════════════════ -->
            <section v-if="ongletActif === 'horaires'" class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <div class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                        <Clock class="h-4 w-4 text-teal-600" /> Horaires
                    </h2>
                    <span class="text-xs text-gray-400">{{ heurs.length }} horaire(s)</span>
                </div>

                <form @submit.prevent="ajouterHeur" class="border-b px-5 py-4 bg-teal-50/40">
                    <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">Nouvel horaire</p>
                    <div class="flex items-center gap-3">
                        <input v-model="heurForm.ouverture" placeholder="Ouverture (ex: 08:00)" maxlength="5" required
                            class="w-48 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400" />
                        <span class="text-gray-400">→</span>
                        <input v-model="heurForm.fermeture" placeholder="Fermeture (ex: 20:00)" maxlength="5" required
                            class="w-48 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400" />
                        <button type="submit" :disabled="enCours"
                            class="flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-60">
                            <Plus class="h-4 w-4" /> Ajouter
                        </button>
                    </div>
                </form>

                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">Ouverture</th>
                            <th class="px-5 py-3 text-left font-medium">Fermeture</th>
                            <th class="px-5 py-3 text-left font-medium">Pharmacies</th>
                            <th class="px-5 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!heurs.length">
                            <td colspan="4" class="px-5 py-6 text-center text-gray-400">Aucun horaire défini.</td>
                        </tr>
                        <tr v-for="h in heurs" :key="h.id" class="hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                <input v-if="editingId === h.id" v-model="heurEdit.ouverture" maxlength="5"
                                    class="w-24 rounded border border-gray-300 px-2 py-1 text-sm" />
                                <span v-else class="font-medium text-gray-800">{{ h.ouverture }}</span>
                            </td>
                            <td class="px-5 py-3 text-gray-600">
                                <input v-if="editingId === h.id" v-model="heurEdit.fermeture" maxlength="5"
                                    class="w-24 rounded border border-gray-300 px-2 py-1 text-sm" />
                                <span v-else>{{ h.fermeture }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ h.pharmacies_count }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === h.id">
                                    <button @click="sauvegarderHeur(h.id)" :disabled="enCours" class="mr-2 text-green-600 hover:text-green-800"><Check class="h-4 w-4" /></button>
                                    <button @click="cancelEdit" class="text-gray-400 hover:text-gray-600"><X class="h-4 w-4" /></button>
                                </template>
                                <template v-else>
                                    <button @click="ouvrirEditHeur(h)" class="mr-3 text-blue-500 hover:text-blue-700"><Pencil class="h-4 w-4" /></button>
                                    <button @click="supprimerHeur(h.id)" class="text-red-400 hover:text-red-600"><Trash2 class="h-4 w-4" /></button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ TYPES DE PHARMACIE ══════════════════ -->
            <section v-if="ongletActif === 'typesPharmacie'" class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <div class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                        <Building2 class="h-4 w-4 text-indigo-600" /> Types de pharmacie
                    </h2>
                    <span class="text-xs text-gray-400">{{ typesPharmacie.length }} type(s)</span>
                </div>

                <form @submit.prevent="ajouterType" class="border-b px-5 py-4 bg-indigo-50/40">
                    <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">Nouveau type</p>
                    <div class="flex gap-3">
                        <input v-model="typeForm.designation" placeholder="Ex : Jour, Nuit, Garde..." required
                            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                        <select v-model="typeForm.heurs_id"
                            class="w-48 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                            <option value="">Horaire (optionnel)</option>
                            <option v-for="h in heurs" :key="h.id" :value="h.id">
                                {{ h.ouverture }} → {{ h.fermeture }}
                            </option>
                        </select>
                        <button type="submit" :disabled="enCours"
                            class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">
                            <Plus class="h-4 w-4" /> Ajouter
                        </button>
                    </div>
                </form>

                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">Désignation</th>
                            <th class="px-5 py-3 text-left font-medium">Horaire associé</th>
                            <th class="px-5 py-3 text-left font-medium">Pharmacies</th>
                            <th class="px-5 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!typesPharmacie.length">
                            <td colspan="4" class="px-5 py-6 text-center text-gray-400">Aucun type de pharmacie.</td>
                        </tr>
                        <tr v-for="t in typesPharmacie" :key="t.id" class="hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                <input v-if="editingId === t.id" v-model="typeEdit.designation"
                                    class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                <span v-else class="font-medium text-gray-800">{{ t.designation }}</span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">
                                <select v-if="editingId === t.id" v-model="typeEdit.heurs_id"
                                    class="rounded border border-gray-300 px-2 py-1 text-sm">
                                    <option value="">— aucun —</option>
                                    <option v-for="h in heurs" :key="h.id" :value="h.id">
                                        {{ h.ouverture }} → {{ h.fermeture }}
                                    </option>
                                </select>
                                <span v-else>
                                    {{ t.heurs ? `${t.heurs.ouverture} → ${t.heurs.fermeture}` : '—' }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ t.pharmacies_count }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === t.id">
                                    <button @click="sauvegarderType(t.id)" :disabled="enCours" class="mr-2 text-green-600 hover:text-green-800"><Check class="h-4 w-4" /></button>
                                    <button @click="cancelEdit" class="text-gray-400 hover:text-gray-600"><X class="h-4 w-4" /></button>
                                </template>
                                <template v-else>
                                    <button @click="ouvrirEditType(t)" class="mr-3 text-blue-500 hover:text-blue-700"><Pencil class="h-4 w-4" /></button>
                                    <button @click="supprimerType(t.id)" class="text-red-400 hover:text-red-600"><Trash2 class="h-4 w-4" /></button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ MOTIFS D'ANNULATION ══════════════════ -->
            <section v-if="ongletActif === 'motifsAnnulation'" class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <div class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                        <RefreshCw class="h-4 w-4 text-blue-600" /> Motifs d'annulation
                    </h2>
                    <span class="text-xs text-gray-400">{{ motifsAnnulation.length }} motif(s)</span>
                </div>
                <div class="border-b px-5 py-4 bg-blue-50/40">
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Le <span class="font-semibold">code</span> (ex. <code class="rounded bg-white/80 px-1 text-xs">medicaments_indisponibles</code>) est stocké sur les commandes : ne le changez que si aucune commande ne l’utilise encore.
                        La <span class="font-semibold">relance</span> contrôle l’affichage du bouton « Relancer la commande » après annulation.
                    </p>
                </div>

                <form @submit.prevent="ajouterMotif" class="border-b px-5 py-4 bg-blue-50/30">
                    <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">Nouveau motif</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                        <div class="md:col-span-3">
                            <label class="mb-1 block text-xs text-gray-500">Code * <span class="font-normal">(a-z, chiffres, _)</span></label>
                            <input
                                v-model="motifForm.slug"
                                required
                                pattern="[a-z][a-z0-9_]*"
                                maxlength="100"
                                placeholder="ex: stock_rupture"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-400"
                            />
                        </div>
                        <div class="md:col-span-4">
                            <label class="mb-1 block text-xs text-gray-500">Libellé affiché *</label>
                            <input
                                v-model="motifForm.label"
                                required
                                maxlength="255"
                                placeholder="Ex. : Rupture de stock"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs text-gray-500">Ordre</label>
                            <input
                                v-model="motifForm.sort_order"
                                type="number"
                                min="0"
                                placeholder="auto"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                            />
                        </div>
                        <div class="md:col-span-2 flex items-center gap-2 pb-2">
                            <input id="motif-new-relance" v-model="motifForm.autorise_relance" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                            <label for="motif-new-relance" class="text-sm text-gray-700">Relance autorisée</label>
                        </div>
                        <div class="md:col-span-1">
                            <button
                                type="submit"
                                :disabled="enCours"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                            >
                                <Plus class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </form>

                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">Libellé</th>
                            <th class="px-5 py-3 text-left font-medium">Code</th>
                            <th class="px-5 py-3 text-center font-medium w-20">Ordre</th>
                            <th class="px-5 py-3 text-center font-medium w-28">Relance</th>
                            <th class="px-5 py-3 text-center font-medium w-24">Cmd.</th>
                            <th class="px-5 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!motifsAnnulation.length">
                            <td colspan="6" class="px-5 py-6 text-center text-gray-400">Aucun motif.</td>
                        </tr>
                        <tr v-for="m in motifsAnnulation" :key="m.id" class="hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                <input
                                    v-if="editingId === m.id"
                                    v-model="motifEdit.label"
                                    maxlength="255"
                                    class="w-full rounded border border-gray-300 px-2 py-1 text-sm"
                                />
                                <span v-else class="font-medium text-gray-800">{{ m.label }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <input
                                    v-if="editingId === m.id"
                                    v-model="motifEdit.slug"
                                    :disabled="m.commandes_count > 0"
                                    maxlength="100"
                                    class="w-full rounded border border-gray-300 px-2 py-1 font-mono text-xs disabled:bg-gray-100 disabled:text-gray-500"
                                />
                                <span v-else class="font-mono text-xs text-gray-600">{{ m.slug }}</span>
                                <p v-if="editingId === m.id && m.commandes_count > 0" class="mt-1 text-[11px] text-amber-700">Code verrouillé ({{ m.commandes_count }} commande(s)).</p>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <input
                                    v-if="editingId === m.id"
                                    v-model="motifEdit.sort_order"
                                    type="number"
                                    min="0"
                                    class="w-16 rounded border border-gray-300 px-2 py-1 text-sm text-center"
                                />
                                <span v-else>{{ m.sort_order }}</span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <input
                                    v-if="editingId === m.id"
                                    v-model="motifEdit.autorise_relance"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600"
                                />
                                <span v-else class="text-xs font-medium" :class="m.autorise_relance ? 'text-green-700' : 'text-gray-400'">
                                    {{ m.autorise_relance ? 'Oui' : 'Non' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ m.commandes_count }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === m.id">
                                    <button type="button" @click="sauvegarderMotif(m.id)" :disabled="enCours" class="mr-2 text-green-600 hover:text-green-800"><Check class="h-4 w-4" /></button>
                                    <button type="button" @click="cancelEdit" class="text-gray-400 hover:text-gray-600"><X class="h-4 w-4" /></button>
                                </template>
                                <template v-else>
                                    <button type="button" @click="ouvrirEditMotif(m)" class="mr-3 text-blue-500 hover:text-blue-700"><Pencil class="h-4 w-4" /></button>
                                    <button
                                        type="button"
                                        @click="supprimerMotif(m.id)"
                                        :disabled="m.commandes_count > 0"
                                        :class="m.commandes_count > 0 ? 'cursor-not-allowed text-gray-300' : 'text-red-400 hover:text-red-600'"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

        </div>
    </AppLayout>
</template>
