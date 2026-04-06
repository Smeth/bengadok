<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Plus,
    Pencil,
    Trash2,
    Check,
    X,
    MapPin,
    CreditCard,
    Truck,
    User,
    Clock,
    Building2,
    RefreshCw,
    CalendarClock,
    Timer,
    FileCheck,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Zone {
    id: number;
    designation: string;
    latitude?: number;
    longitude?: number;
    pharmacies_count: number;
}
interface ModePaiement {
    id: number;
    designation: string;
    commandes_count: number;
}
interface MontantLivraison {
    id: number;
    designation: number;
    commandes_count: number;
}
interface Livreur {
    id: number;
    nom: string;
    prenom: string;
    tel: string;
    commandes_count: number;
}
interface Heur {
    id: number;
    ouverture: string;
    fermeture: string;
    pharmacies_count: number;
}
interface TypePharmacie {
    id: number;
    designation: string;
    heurs_id?: number;
    heurs?: Heur;
    pharmacies_count: number;
}
interface MotifAnnulationRow {
    id: number;
    slug: string;
    label: string;
    autorise_relance: boolean;
    sort_order: number;
    commandes_count: number;
}

interface ClientFrequenceRow {
    id: number;
    designation: string;
    slug: string;
    commandes_minimum: number;
    commandes_maximum: number | null;
    intervalle_max_jours: number | null;
    priorite: number;
}

interface AppSettingsProps {
    delai_relance_meme_pharmacie_heures: number;
}

interface OrdonnanceVerificationSettings {
    enabled: boolean;
    max_prescription_age_days: number;
    threshold_pass: number;
    threshold_review: number;
    /** queue = file d'attente ; immediate = pendant la requête d'upload */
    execution_mode: 'queue' | 'immediate';
    block_pass_on_duplicate: boolean;
    tesseract_binary: string;
    rule_weights: Record<string, number>;
    keywords_prescriber: string[];
    keywords_patient: string[];
    keywords_medicament: string[];
}

const props = withDefaults(
    defineProps<{
        zones: Zone[];
        modesPaiement: ModePaiement[];
        montantsLivraison: MontantLivraison[];
        livreurs: Livreur[];
        heurs: Heur[];
        typesPharmacie: TypePharmacie[];
        motifsAnnulation: MotifAnnulationRow[];
        clientFrequences: ClientFrequenceRow[];
        appSettings: AppSettingsProps;
        ordonnanceVerificationSettings: OrdonnanceVerificationSettings;
        onglet: string | null;
    }>(),
    {
        motifsAnnulation: () => [],
        clientFrequences: () => [],
        appSettings: () => ({ delai_relance_meme_pharmacie_heures: 24 }),
        ordonnanceVerificationSettings: () => ({
            enabled: true,
            execution_mode: 'queue' as const,
            max_prescription_age_days: 180,
            threshold_pass: 70,
            threshold_review: 45,
            block_pass_on_duplicate: true,
            tesseract_binary: 'tesseract',
            rule_weights: {},
            keywords_prescriber: [],
            keywords_patient: [],
            keywords_medicament: [],
        }),
        onglet: null,
    },
);

const page = usePage();
const flashStatus = computed(
    () => (page.props.flash as { status?: string })?.status,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Configuration', href: '/settings/parametres' },
];

// ─── Onglet actif ─────────────────────────────────────────────────────────────

const onglets = [
    { id: 'zones', label: 'Zones', icon: MapPin },
    { id: 'modesPaiement', label: 'Modes de paiement', icon: CreditCard },
    { id: 'montantsLivraison', label: 'Montants livraison', icon: Truck },
    { id: 'livreurs', label: 'Livreurs', icon: User },
    { id: 'horaires', label: 'Horaires', icon: Clock },
    { id: 'typesPharmacie', label: 'Types pharmacie', icon: Building2 },
    {
        id: 'clientFrequences',
        label: 'Fréquences clients',
        icon: CalendarClock,
    },
    { id: 'relanceCommande', label: 'Relance commandes', icon: Timer },
    {
        id: 'ordonnanceVerification',
        label: 'Ordonnances (OCR)',
        icon: FileCheck,
    },
    { id: 'motifsAnnulation', label: "Motifs d'annulation", icon: RefreshCw },
] as const;

type OngletId = (typeof onglets)[number]['id'];
const ongletActif = ref<OngletId>('zones');

function isOngletId(v: string): v is OngletId {
    return (onglets as readonly { id: string }[]).some((t) => t.id === v);
}

watch(
    () => props.onglet,
    (o) => {
        if (o && isOngletId(o)) {
            ongletActif.value = o;
        }
    },
    { immediate: true },
);

function selectOnglet(id: OngletId) {
    ongletActif.value = id;
    editingId.value = null;
    freqModalOpen.value = false;
    const q = id === 'zones' ? {} : { onglet: id };
    router.get('/settings/parametres', q, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

// ─── Utilitaires formulaire ───────────────────────────────────────────────────

const enCours = ref(false);
const editingId = ref<number | null>(null);

function startEdit(id: number) {
    editingId.value = id;
}
function cancelEdit() {
    editingId.value = null;
}

function submit(
    url: string,
    data: Record<string, unknown>,
    method: 'post' | 'patch' | 'delete' = 'post',
) {
    enCours.value = true;
    router[method](url, data, {
        onSuccess: () => {
            editingId.value = null;
        },
        onFinish: () => {
            enCours.value = false;
        },
    });
}

// ─── Zones ────────────────────────────────────────────────────────────────────

const zoneForm = ref({ designation: '', latitude: '', longitude: '' });
const zoneEdit = ref({ designation: '', latitude: '', longitude: '' });

function ajouterZone() {
    submit('/settings/parametres/zones', zoneForm.value);
    zoneForm.value = { designation: '', latitude: '', longitude: '' };
}
function ouvrirEditZone(z: Zone) {
    zoneEdit.value = {
        designation: z.designation,
        latitude: String(z.latitude ?? ''),
        longitude: String(z.longitude ?? ''),
    };
    startEdit(z.id);
}
function sauvegarderZone(id: number) {
    submit(`/settings/parametres/zones/${id}`, zoneEdit.value, 'patch');
}
function supprimerZone(id: number) {
    if (confirm('Supprimer cette zone ?'))
        submit(`/settings/parametres/zones/${id}`, {}, 'delete');
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
    submit(
        `/settings/parametres/modes-paiement/${id}`,
        modeEdit.value,
        'patch',
    );
}
function supprimerMode(id: number) {
    if (confirm('Supprimer ce mode de paiement ?'))
        submit(`/settings/parametres/modes-paiement/${id}`, {}, 'delete');
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
    submit(
        `/settings/parametres/montants-livraison/${id}`,
        montantEdit.value,
        'patch',
    );
}
function supprimerMontant(id: number) {
    if (confirm('Supprimer ce montant de livraison ?'))
        submit(`/settings/parametres/montants-livraison/${id}`, {}, 'delete');
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
    if (confirm('Supprimer ce livreur ?'))
        submit(`/settings/parametres/livreurs/${id}`, {}, 'delete');
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
    if (confirm('Supprimer cet horaire ?'))
        submit(`/settings/parametres/heurs/${id}`, {}, 'delete');
}

// ─── Types de pharmacie ───────────────────────────────────────────────────────

const typeForm = ref({ designation: '', heurs_id: '' });
const typeEdit = ref({ designation: '', heurs_id: '' });

function ajouterType() {
    submit('/settings/parametres/types-pharmacie', typeForm.value);
    typeForm.value = { designation: '', heurs_id: '' };
}
function ouvrirEditType(t: TypePharmacie) {
    typeEdit.value = {
        designation: t.designation,
        heurs_id: String(t.heurs_id ?? ''),
    };
    startEdit(t.id);
}
function sauvegarderType(id: number) {
    submit(
        `/settings/parametres/types-pharmacie/${id}`,
        typeEdit.value,
        'patch',
    );
}
function supprimerType(id: number) {
    if (confirm('Supprimer ce type de pharmacie ?'))
        submit(`/settings/parametres/types-pharmacie/${id}`, {}, 'delete');
}

// ─── Motifs d'annulation ─────────────────────────────────────────────────────

const motifForm = ref({
    slug: '',
    label: '',
    sort_order: '',
    autorise_relance: false,
});
const motifEdit = ref({
    slug: '',
    label: '',
    sort_order: '',
    autorise_relance: false,
});

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
    motifForm.value = {
        slug: '',
        label: '',
        sort_order: '',
        autorise_relance: false,
    };
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
    submit(
        `/settings/parametres/motifs-annulation/${id}`,
        {
            slug: motifEdit.value.slug.trim(),
            label: motifEdit.value.label.trim(),
            autorise_relance: motifEdit.value.autorise_relance,
            sort_order:
                motifEdit.value.sort_order === ''
                    ? undefined
                    : Number(motifEdit.value.sort_order),
        },
        'patch',
    );
}

function supprimerMotif(id: number) {
    if (
        confirm(
            'Supprimer ce motif ? Impossible s’il est utilisé par des commandes.',
        )
    ) {
        submit(`/settings/parametres/motifs-annulation/${id}`, {}, 'delete');
    }
}

// ─── Fréquences clients (segmentation liste clients) ─────────────────────────

const freqModalOpen = ref(false);
const freqEditing = ref<ClientFrequenceRow | null>(null);
const freqForm = ref({
    designation: '',
    slug: '',
    commandes_minimum: '0',
    commandes_maximum: '',
    intervalle_max_jours: '',
    priorite: '10',
});

function closeFreqModal() {
    freqModalOpen.value = false;
    freqEditing.value = null;
}

function openFreqCreate() {
    freqEditing.value = null;
    freqForm.value = {
        designation: '',
        slug: '',
        commandes_minimum: '0',
        commandes_maximum: '',
        intervalle_max_jours: '',
        priorite: '10',
    };
    freqModalOpen.value = true;
}

function openFreqEdit(row: ClientFrequenceRow) {
    freqEditing.value = row;
    freqForm.value = {
        designation: row.designation,
        slug: row.slug,
        commandes_minimum: String(row.commandes_minimum),
        commandes_maximum:
            row.commandes_maximum != null ? String(row.commandes_maximum) : '',
        intervalle_max_jours:
            row.intervalle_max_jours != null
                ? String(row.intervalle_max_jours)
                : '',
        priorite: String(row.priorite),
    };
    freqModalOpen.value = true;
}

function optInt(raw: string): number | undefined {
    const t = raw.trim();
    if (t === '') return undefined;
    const n = parseInt(t, 10);
    return Number.isFinite(n) ? n : undefined;
}

function payloadFreq() {
    const min = parseInt(freqForm.value.commandes_minimum, 10);
    const prio = parseInt(freqForm.value.priorite, 10);
    return {
        designation: freqForm.value.designation.trim(),
        slug: freqForm.value.slug.trim() || undefined,
        commandes_minimum: Number.isFinite(min) ? min : 0,
        commandes_maximum: optInt(freqForm.value.commandes_maximum),
        intervalle_max_jours: optInt(freqForm.value.intervalle_max_jours),
        priorite: Number.isFinite(prio) ? prio : 0,
    };
}

function submitFreq() {
    const p = payloadFreq();
    if (!p.designation) return;
    enCours.value = true;
    if (freqEditing.value) {
        router.patch(
            `/settings/parametres/client-frequences/${freqEditing.value.id}`,
            p,
            {
                preserveScroll: true,
                onSuccess: () => closeFreqModal(),
                onFinish: () => {
                    enCours.value = false;
                },
            },
        );
    } else {
        router.post('/settings/parametres/client-frequences', p, {
            preserveScroll: true,
            onSuccess: () => closeFreqModal(),
            onFinish: () => {
                enCours.value = false;
            },
        });
    }
}

function destroyFreq(row: ClientFrequenceRow) {
    if (!confirm(`Supprimer la fréquence « ${row.designation} » ?`)) return;
    enCours.value = true;
    router.delete(`/settings/parametres/client-frequences/${row.id}`, {
        preserveScroll: true,
        onFinish: () => {
            enCours.value = false;
        },
    });
}

const relanceDelaiForm = ref({ delai_relance_meme_pharmacie_heures: '24' });

watch(
    () => props.appSettings.delai_relance_meme_pharmacie_heures,
    (v) => {
        relanceDelaiForm.value.delai_relance_meme_pharmacie_heures = String(
            v ?? 0,
        );
    },
    { immediate: true },
);

function sauverRelanceDelai() {
    const n = parseInt(
        relanceDelaiForm.value.delai_relance_meme_pharmacie_heures,
        10,
    );
    if (!Number.isFinite(n) || n < 0) return;
    submit(
        '/settings/parametres/relance-delai',
        { delai_relance_meme_pharmacie_heures: n },
        'patch',
    );
}

const OV_RULE_KEYS = [
    { key: 'date_found', label: 'Date repérée dans le document' },
    { key: 'date_not_future', label: 'Date de prescription non future' },
    {
        key: 'date_within_max_age',
        label: 'Prescription dans la durée autorisée (ancienneté max.)',
    },
    { key: 'prescriber_keywords', label: 'Mots-clés prescripteur / structure' },
    { key: 'patient_keywords', label: 'Mots-clés patient' },
    { key: 'medicament_keywords', label: 'Mots-clés médicament / posologie' },
    { key: 'no_duplicate_file', label: 'Fichier non déjà utilisé (empreinte)' },
] as const;

const DEFAULT_OV_WEIGHTS: Record<string, number> = {
    date_found: 12,
    date_not_future: 20,
    date_within_max_age: 25,
    prescriber_keywords: 12,
    patient_keywords: 8,
    medicament_keywords: 13,
    no_duplicate_file: 10,
};

const ovForm = ref({
    enabled: true,
    execution_mode: 'queue' as 'queue' | 'immediate',
    max_prescription_age_days: 180,
    threshold_pass: 70,
    threshold_review: 45,
    block_pass_on_duplicate: true,
    tesseract_binary: 'tesseract',
    rule_weights: {} as Record<string, number>,
    kw_prescriber: '',
    kw_patient: '',
    kw_medicament: '',
});

watch(
    () => props.ordonnanceVerificationSettings,
    (s) => {
        if (!s) return;
        ovForm.value.enabled = s.enabled;
        ovForm.value.execution_mode =
            s.execution_mode === 'immediate' ? 'immediate' : 'queue';
        ovForm.value.max_prescription_age_days = s.max_prescription_age_days;
        ovForm.value.threshold_pass = s.threshold_pass;
        ovForm.value.threshold_review = s.threshold_review;
        ovForm.value.block_pass_on_duplicate = s.block_pass_on_duplicate;
        ovForm.value.tesseract_binary = s.tesseract_binary;
        ovForm.value.rule_weights = {
            ...DEFAULT_OV_WEIGHTS,
            ...(s.rule_weights || {}),
        };
        ovForm.value.kw_prescriber = (s.keywords_prescriber || []).join('\n');
        ovForm.value.kw_patient = (s.keywords_patient || []).join('\n');
        ovForm.value.kw_medicament = (s.keywords_medicament || []).join('\n');
    },
    { immediate: true, deep: true },
);

function sauverOrdonnanceVerification() {
    const rw: Record<string, number> = {};
    for (const { key } of OV_RULE_KEYS) {
        const v = Number(ovForm.value.rule_weights[key]);
        rw[key] = Number.isFinite(v) && v >= 0 ? v : 0;
    }
    submit(
        '/settings/parametres/ordonnance-verification',
        {
            enabled: ovForm.value.enabled,
            execution_mode: ovForm.value.execution_mode,
            max_prescription_age_days: ovForm.value.max_prescription_age_days,
            threshold_pass: ovForm.value.threshold_pass,
            threshold_review: ovForm.value.threshold_review,
            block_pass_on_duplicate: ovForm.value.block_pass_on_duplicate,
            tesseract_binary: ovForm.value.tesseract_binary.trim() || 'tesseract',
            rule_weights: rw,
            keywords_prescriber: ovForm.value.kw_prescriber
                .split('\n')
                .map((x) => x.trim())
                .filter(Boolean),
            keywords_patient: ovForm.value.kw_patient
                .split('\n')
                .map((x) => x.trim())
                .filter(Boolean),
            keywords_medicament: ovForm.value.kw_medicament
                .split('\n')
                .map((x) => x.trim())
                .filter(Boolean),
        },
        'patch',
    );
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
                    Configurez les données de référence utilisées dans
                    l'application.
                </p>
            </div>

            <p
                v-if="flashStatus"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"
            >
                {{ flashStatus }}
            </p>

            <!-- Onglets -->
            <div class="flex flex-wrap gap-1 rounded-xl bg-gray-100 p-1">
                <button
                    v-for="o in onglets"
                    :key="o.id"
                    type="button"
                    @click="selectOnglet(o.id)"
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
            <section
                v-if="ongletActif === 'zones'"
                class="rounded-xl border border-gray-200 bg-white overflow-hidden"
            >
                <div
                    class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between"
                >
                    <h2
                        class="font-semibold text-gray-700 flex items-center gap-2"
                    >
                        <MapPin class="h-4 w-4 text-green-600" /> Zones de
                        Brazzaville
                    </h2>
                    <span class="text-xs text-gray-400"
                        >{{ zones.length }} zone(s)</span
                    >
                </div>

                <!-- Formulaire ajout -->
                <form
                    @submit.prevent="ajouterZone"
                    class="border-b px-5 py-4 bg-green-50/40"
                >
                    <p
                        class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400"
                    >
                        Nouvelle zone
                    </p>
                    <div class="grid grid-cols-3 gap-3">
                        <input
                            v-model="zoneForm.designation"
                            placeholder="Nom de la zone *"
                            required
                            class="col-span-3 sm:col-span-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                        />
                        <input
                            v-model="zoneForm.latitude"
                            type="number"
                            step="any"
                            placeholder="Latitude (optionnel)"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                        />
                        <input
                            v-model="zoneForm.longitude"
                            type="number"
                            step="any"
                            placeholder="Longitude (optionnel)"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="enCours"
                        class="mt-3 flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-60"
                    >
                        <Plus class="h-4 w-4" /> Ajouter
                    </button>
                </form>

                <!-- Liste -->
                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">
                                Désignation
                            </th>
                            <th class="px-5 py-3 text-left font-medium">
                                Coordonnées
                            </th>
                            <th class="px-5 py-3 text-left font-medium">
                                Pharmacies
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!zones.length">
                            <td
                                colspan="4"
                                class="px-5 py-6 text-center text-gray-400"
                            >
                                Aucune zone enregistrée.
                            </td>
                        </tr>
                        <tr
                            v-for="z in zones"
                            :key="z.id"
                            class="hover:bg-gray-50/50"
                        >
                            <td class="px-5 py-3">
                                <input
                                    v-if="editingId === z.id"
                                    v-model="zoneEdit.designation"
                                    class="w-full rounded border border-gray-300 px-2 py-1 text-sm"
                                />
                                <span
                                    v-else
                                    class="font-medium text-gray-800"
                                    >{{ z.designation }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-gray-500">
                                <div
                                    v-if="editingId === z.id"
                                    class="flex gap-2"
                                >
                                    <input
                                        v-model="zoneEdit.latitude"
                                        placeholder="Lat"
                                        type="number"
                                        step="any"
                                        class="w-24 rounded border border-gray-300 px-2 py-1 text-sm"
                                    />
                                    <input
                                        v-model="zoneEdit.longitude"
                                        placeholder="Lng"
                                        type="number"
                                        step="any"
                                        class="w-24 rounded border border-gray-300 px-2 py-1 text-sm"
                                    />
                                </div>
                                <span v-else>
                                    {{
                                        z.latitude
                                            ? `${z.latitude}, ${z.longitude}`
                                            : '—'
                                    }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700"
                                >
                                    {{ z.pharmacies_count }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === z.id">
                                    <button
                                        @click="sauvegarderZone(z.id)"
                                        :disabled="enCours"
                                        class="mr-2 text-green-600 hover:text-green-800"
                                    >
                                        <Check class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="cancelEdit"
                                        class="text-gray-400 hover:text-gray-600"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        @click="ouvrirEditZone(z)"
                                        class="mr-3 text-blue-500 hover:text-blue-700"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="supprimerZone(z.id)"
                                        class="text-red-400 hover:text-red-600"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ MODES DE PAIEMENT ══════════════════ -->
            <section
                v-if="ongletActif === 'modesPaiement'"
                class="rounded-xl border border-gray-200 bg-white overflow-hidden"
            >
                <div
                    class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between"
                >
                    <h2
                        class="font-semibold text-gray-700 flex items-center gap-2"
                    >
                        <CreditCard class="h-4 w-4 text-blue-600" /> Modes de
                        paiement
                    </h2>
                    <span class="text-xs text-gray-400"
                        >{{ modesPaiement.length }} mode(s)</span
                    >
                </div>

                <form
                    @submit.prevent="ajouterMode"
                    class="border-b px-5 py-4 bg-blue-50/40"
                >
                    <p
                        class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400"
                    >
                        Nouveau mode
                    </p>
                    <div class="flex gap-3">
                        <input
                            v-model="modeForm.designation"
                            placeholder="Ex : Mobile Money, Espèces..."
                            required
                            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                        />
                        <button
                            type="submit"
                            :disabled="enCours"
                            class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                        >
                            <Plus class="h-4 w-4" /> Ajouter
                        </button>
                    </div>
                </form>

                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">
                                Désignation
                            </th>
                            <th class="px-5 py-3 text-left font-medium">
                                Commandes liées
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!modesPaiement.length">
                            <td
                                colspan="3"
                                class="px-5 py-6 text-center text-gray-400"
                            >
                                Aucun mode de paiement.
                            </td>
                        </tr>
                        <tr
                            v-for="m in modesPaiement"
                            :key="m.id"
                            class="hover:bg-gray-50/50"
                        >
                            <td class="px-5 py-3">
                                <input
                                    v-if="editingId === m.id"
                                    v-model="modeEdit.designation"
                                    class="w-full rounded border border-gray-300 px-2 py-1 text-sm"
                                />
                                <span
                                    v-else
                                    class="font-medium text-gray-800"
                                    >{{ m.designation }}</span
                                >
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
                                    >{{ m.commandes_count }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === m.id">
                                    <button
                                        @click="sauvegarderMode(m.id)"
                                        :disabled="enCours"
                                        class="mr-2 text-green-600 hover:text-green-800"
                                    >
                                        <Check class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="cancelEdit"
                                        class="text-gray-400 hover:text-gray-600"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        @click="ouvrirEditMode(m)"
                                        class="mr-3 text-blue-500 hover:text-blue-700"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="supprimerMode(m.id)"
                                        class="text-red-400 hover:text-red-600"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ MONTANTS LIVRAISON ══════════════════ -->
            <section
                v-if="ongletActif === 'montantsLivraison'"
                class="rounded-xl border border-gray-200 bg-white overflow-hidden"
            >
                <div
                    class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between"
                >
                    <h2
                        class="font-semibold text-gray-700 flex items-center gap-2"
                    >
                        <Truck class="h-4 w-4 text-orange-600" /> Montants de
                        livraison
                    </h2>
                    <span class="text-xs text-gray-400"
                        >{{ montantsLivraison.length }} tarif(s)</span
                    >
                </div>

                <form
                    @submit.prevent="ajouterMontant"
                    class="border-b px-5 py-4 bg-orange-50/40"
                >
                    <p
                        class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400"
                    >
                        Nouveau tarif
                    </p>
                    <div class="flex gap-3 items-center">
                        <input
                            v-model="montantForm.designation"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Montant en FCFA *"
                            required
                            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                        />
                        <span class="text-sm text-gray-500 font-medium"
                            >FCFA</span
                        >
                        <button
                            type="submit"
                            :disabled="enCours"
                            class="flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600 disabled:opacity-60"
                        >
                            <Plus class="h-4 w-4" /> Ajouter
                        </button>
                    </div>
                </form>

                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">
                                Montant (FCFA)
                            </th>
                            <th class="px-5 py-3 text-left font-medium">
                                Commandes liées
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!montantsLivraison.length">
                            <td
                                colspan="3"
                                class="px-5 py-6 text-center text-gray-400"
                            >
                                Aucun tarif de livraison.
                            </td>
                        </tr>
                        <tr
                            v-for="m in montantsLivraison"
                            :key="m.id"
                            class="hover:bg-gray-50/50"
                        >
                            <td class="px-5 py-3">
                                <div
                                    v-if="editingId === m.id"
                                    class="flex items-center gap-2"
                                >
                                    <input
                                        v-model="montantEdit.designation"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-32 rounded border border-gray-300 px-2 py-1 text-sm"
                                    />
                                    <span class="text-xs text-gray-400"
                                        >FCFA</span
                                    >
                                </div>
                                <span v-else class="font-medium text-gray-800">
                                    {{
                                        Number(m.designation).toLocaleString(
                                            'fr-FR',
                                        )
                                    }}
                                    FCFA
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
                                    >{{ m.commandes_count }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === m.id">
                                    <button
                                        @click="sauvegarderMontant(m.id)"
                                        :disabled="enCours"
                                        class="mr-2 text-green-600 hover:text-green-800"
                                    >
                                        <Check class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="cancelEdit"
                                        class="text-gray-400 hover:text-gray-600"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        @click="ouvrirEditMontant(m)"
                                        class="mr-3 text-blue-500 hover:text-blue-700"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="supprimerMontant(m.id)"
                                        class="text-red-400 hover:text-red-600"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ LIVREURS ══════════════════ -->
            <section
                v-if="ongletActif === 'livreurs'"
                class="rounded-xl border border-gray-200 bg-white overflow-hidden"
            >
                <div
                    class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between"
                >
                    <h2
                        class="font-semibold text-gray-700 flex items-center gap-2"
                    >
                        <User class="h-4 w-4 text-purple-600" /> Livreurs
                    </h2>
                    <span class="text-xs text-gray-400"
                        >{{ livreurs.length }} livreur(s)</span
                    >
                </div>

                <form
                    @submit.prevent="ajouterLivreur"
                    class="border-b px-5 py-4 bg-purple-50/40"
                >
                    <p
                        class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400"
                    >
                        Nouveau livreur
                    </p>
                    <div class="grid grid-cols-3 gap-3">
                        <input
                            v-model="livreurForm.prenom"
                            placeholder="Prénom *"
                            required
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400"
                        />
                        <input
                            v-model="livreurForm.nom"
                            placeholder="Nom *"
                            required
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400"
                        />
                        <input
                            v-model="livreurForm.tel"
                            placeholder="Téléphone *"
                            required
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="enCours"
                        class="mt-3 flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-60"
                    >
                        <Plus class="h-4 w-4" /> Ajouter
                    </button>
                </form>

                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">
                                Prénom & Nom
                            </th>
                            <th class="px-5 py-3 text-left font-medium">
                                Téléphone
                            </th>
                            <th class="px-5 py-3 text-left font-medium">
                                Commandes
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!livreurs.length">
                            <td
                                colspan="4"
                                class="px-5 py-6 text-center text-gray-400"
                            >
                                Aucun livreur enregistré.
                            </td>
                        </tr>
                        <tr
                            v-for="l in livreurs"
                            :key="l.id"
                            class="hover:bg-gray-50/50"
                        >
                            <td class="px-5 py-3">
                                <div
                                    v-if="editingId === l.id"
                                    class="flex gap-2"
                                >
                                    <input
                                        v-model="livreurEdit.prenom"
                                        placeholder="Prénom"
                                        class="w-28 rounded border border-gray-300 px-2 py-1 text-sm"
                                    />
                                    <input
                                        v-model="livreurEdit.nom"
                                        placeholder="Nom"
                                        class="w-28 rounded border border-gray-300 px-2 py-1 text-sm"
                                    />
                                </div>
                                <span v-else class="font-medium text-gray-800"
                                    >{{ l.prenom }} {{ l.nom }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-gray-600">
                                <input
                                    v-if="editingId === l.id"
                                    v-model="livreurEdit.tel"
                                    class="w-32 rounded border border-gray-300 px-2 py-1 text-sm"
                                />
                                <span v-else>{{ l.tel }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
                                    >{{ l.commandes_count }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === l.id">
                                    <button
                                        @click="sauvegarderLivreur(l.id)"
                                        :disabled="enCours"
                                        class="mr-2 text-green-600 hover:text-green-800"
                                    >
                                        <Check class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="cancelEdit"
                                        class="text-gray-400 hover:text-gray-600"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        @click="ouvrirEditLivreur(l)"
                                        class="mr-3 text-blue-500 hover:text-blue-700"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="supprimerLivreur(l.id)"
                                        class="text-red-400 hover:text-red-600"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ HORAIRES ══════════════════ -->
            <section
                v-if="ongletActif === 'horaires'"
                class="rounded-xl border border-gray-200 bg-white overflow-hidden"
            >
                <div
                    class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between"
                >
                    <h2
                        class="font-semibold text-gray-700 flex items-center gap-2"
                    >
                        <Clock class="h-4 w-4 text-teal-600" /> Horaires
                    </h2>
                    <span class="text-xs text-gray-400"
                        >{{ heurs.length }} horaire(s)</span
                    >
                </div>

                <form
                    @submit.prevent="ajouterHeur"
                    class="border-b px-5 py-4 bg-teal-50/40"
                >
                    <p
                        class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400"
                    >
                        Nouvel horaire
                    </p>
                    <div class="flex items-center gap-3">
                        <input
                            v-model="heurForm.ouverture"
                            placeholder="Ouverture (ex: 08:00)"
                            maxlength="5"
                            required
                            class="w-48 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400"
                        />
                        <span class="text-gray-400">→</span>
                        <input
                            v-model="heurForm.fermeture"
                            placeholder="Fermeture (ex: 20:00)"
                            maxlength="5"
                            required
                            class="w-48 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400"
                        />
                        <button
                            type="submit"
                            :disabled="enCours"
                            class="flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-60"
                        >
                            <Plus class="h-4 w-4" /> Ajouter
                        </button>
                    </div>
                </form>

                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">
                                Ouverture
                            </th>
                            <th class="px-5 py-3 text-left font-medium">
                                Fermeture
                            </th>
                            <th class="px-5 py-3 text-left font-medium">
                                Pharmacies
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!heurs.length">
                            <td
                                colspan="4"
                                class="px-5 py-6 text-center text-gray-400"
                            >
                                Aucun horaire défini.
                            </td>
                        </tr>
                        <tr
                            v-for="h in heurs"
                            :key="h.id"
                            class="hover:bg-gray-50/50"
                        >
                            <td class="px-5 py-3">
                                <input
                                    v-if="editingId === h.id"
                                    v-model="heurEdit.ouverture"
                                    maxlength="5"
                                    class="w-24 rounded border border-gray-300 px-2 py-1 text-sm"
                                />
                                <span
                                    v-else
                                    class="font-medium text-gray-800"
                                    >{{ h.ouverture }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-gray-600">
                                <input
                                    v-if="editingId === h.id"
                                    v-model="heurEdit.fermeture"
                                    maxlength="5"
                                    class="w-24 rounded border border-gray-300 px-2 py-1 text-sm"
                                />
                                <span v-else>{{ h.fermeture }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
                                    >{{ h.pharmacies_count }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === h.id">
                                    <button
                                        @click="sauvegarderHeur(h.id)"
                                        :disabled="enCours"
                                        class="mr-2 text-green-600 hover:text-green-800"
                                    >
                                        <Check class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="cancelEdit"
                                        class="text-gray-400 hover:text-gray-600"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        @click="ouvrirEditHeur(h)"
                                        class="mr-3 text-blue-500 hover:text-blue-700"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="supprimerHeur(h.id)"
                                        class="text-red-400 hover:text-red-600"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ TYPES DE PHARMACIE ══════════════════ -->
            <section
                v-if="ongletActif === 'typesPharmacie'"
                class="rounded-xl border border-gray-200 bg-white overflow-hidden"
            >
                <div
                    class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between"
                >
                    <h2
                        class="font-semibold text-gray-700 flex items-center gap-2"
                    >
                        <Building2 class="h-4 w-4 text-indigo-600" /> Types de
                        pharmacie
                    </h2>
                    <span class="text-xs text-gray-400"
                        >{{ typesPharmacie.length }} type(s)</span
                    >
                </div>

                <form
                    @submit.prevent="ajouterType"
                    class="border-b px-5 py-4 bg-indigo-50/40"
                >
                    <p
                        class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400"
                    >
                        Nouveau type
                    </p>
                    <div class="flex gap-3">
                        <input
                            v-model="typeForm.designation"
                            placeholder="Ex : Jour, Nuit, Garde..."
                            required
                            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        />
                        <select
                            v-model="typeForm.heurs_id"
                            class="w-48 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        >
                            <option value="">Horaire (optionnel)</option>
                            <option
                                v-for="h in heurs"
                                :key="h.id"
                                :value="h.id"
                            >
                                {{ h.ouverture }} → {{ h.fermeture }}
                            </option>
                        </select>
                        <button
                            type="submit"
                            :disabled="enCours"
                            class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                        >
                            <Plus class="h-4 w-4" /> Ajouter
                        </button>
                    </div>
                </form>

                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">
                                Désignation
                            </th>
                            <th class="px-5 py-3 text-left font-medium">
                                Horaire associé
                            </th>
                            <th class="px-5 py-3 text-left font-medium">
                                Pharmacies
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!typesPharmacie.length">
                            <td
                                colspan="4"
                                class="px-5 py-6 text-center text-gray-400"
                            >
                                Aucun type de pharmacie.
                            </td>
                        </tr>
                        <tr
                            v-for="t in typesPharmacie"
                            :key="t.id"
                            class="hover:bg-gray-50/50"
                        >
                            <td class="px-5 py-3">
                                <input
                                    v-if="editingId === t.id"
                                    v-model="typeEdit.designation"
                                    class="w-full rounded border border-gray-300 px-2 py-1 text-sm"
                                />
                                <span
                                    v-else
                                    class="font-medium text-gray-800"
                                    >{{ t.designation }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-gray-500">
                                <select
                                    v-if="editingId === t.id"
                                    v-model="typeEdit.heurs_id"
                                    class="rounded border border-gray-300 px-2 py-1 text-sm"
                                >
                                    <option value="">— aucun —</option>
                                    <option
                                        v-for="h in heurs"
                                        :key="h.id"
                                        :value="h.id"
                                    >
                                        {{ h.ouverture }} → {{ h.fermeture }}
                                    </option>
                                </select>
                                <span v-else>
                                    {{
                                        t.heurs
                                            ? `${t.heurs.ouverture} → ${t.heurs.fermeture}`
                                            : '—'
                                    }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
                                    >{{ t.pharmacies_count }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === t.id">
                                    <button
                                        @click="sauvegarderType(t.id)"
                                        :disabled="enCours"
                                        class="mr-2 text-green-600 hover:text-green-800"
                                    >
                                        <Check class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="cancelEdit"
                                        class="text-gray-400 hover:text-gray-600"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        @click="ouvrirEditType(t)"
                                        class="mr-3 text-blue-500 hover:text-blue-700"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="supprimerType(t.id)"
                                        class="text-red-400 hover:text-red-600"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ══════════════════ FRÉQUENCES CLIENTS ══════════════════ -->
            <section
                v-if="ongletActif === 'clientFrequences'"
                class="rounded-xl border border-gray-200 bg-white overflow-hidden"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b bg-gray-50 px-5 py-3"
                >
                    <h2
                        class="flex items-center gap-2 font-semibold text-gray-700"
                    >
                        <CalendarClock class="h-4 w-4 text-violet-600" />
                        Fréquences clients
                    </h2>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs text-gray-400"
                            >{{ clientFrequences.length }} règle(s)</span
                        >
                        <Button
                            type="button"
                            class="h-9 gap-2 bg-violet-600 text-white hover:bg-violet-700"
                            @click="openFreqCreate"
                        >
                            <Plus class="h-4 w-4" />
                            Nouvelle fréquence
                        </Button>
                    </div>
                </div>
                <div class="border-b bg-violet-50/40 px-5 py-4">
                    <p class="text-sm leading-relaxed text-gray-600">
                        Utilisé pour le filtre « Toutes les fréquences » sur la
                        <span class="font-semibold">liste des clients</span> et
                        le libellé sur chaque carte. La règle à la
                        <span class="font-semibold">priorité</span> la plus
                        élevée qui correspond au client (nombre de commandes
                        comptabilisées, écart moyen en jours entre commandes)
                        s’applique en premier.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead
                            class="border-b bg-gray-50 text-xs text-gray-500"
                        >
                            <tr>
                                <th class="px-5 py-3 text-left font-medium">
                                    Libellé
                                </th>
                                <th class="px-5 py-3 text-left font-medium">
                                    Slug
                                </th>
                                <th class="px-5 py-3 text-right font-medium">
                                    Cmd min
                                </th>
                                <th class="px-5 py-3 text-right font-medium">
                                    Cmd max
                                </th>
                                <th class="px-5 py-3 text-right font-medium">
                                    Intervalle max (j)
                                </th>
                                <th class="px-5 py-3 text-right font-medium">
                                    Priorité
                                </th>
                                <th
                                    class="px-5 py-3 text-right font-medium"
                                ></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="!clientFrequences.length">
                                <td
                                    colspan="7"
                                    class="px-5 py-6 text-center text-gray-400"
                                >
                                    Aucune fréquence définie.
                                </td>
                            </tr>
                            <tr
                                v-for="f in clientFrequences"
                                :key="f.id"
                                class="hover:bg-gray-50/50"
                            >
                                <td class="px-5 py-3 font-medium text-gray-800">
                                    {{ f.designation }}
                                </td>
                                <td
                                    class="px-5 py-3 font-mono text-xs text-gray-600"
                                >
                                    {{ f.slug }}
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums">
                                    {{ f.commandes_minimum }}
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums">
                                    {{ f.commandes_maximum ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums">
                                    {{ f.intervalle_max_jours ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums">
                                    {{ f.priorite }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button
                                        type="button"
                                        class="mr-3 text-blue-500 hover:text-blue-700"
                                        @click="openFreqEdit(f)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-red-400 hover:text-red-600"
                                        @click="destroyFreq(f)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Dialog
                    :open="freqModalOpen"
                    @update:open="(v: boolean) => !v && closeFreqModal()"
                >
                    <DialogContent
                        class="max-h-[90vh] max-w-lg overflow-y-auto"
                    >
                        <DialogHeader>
                            <DialogTitle>{{
                                freqEditing
                                    ? 'Modifier la fréquence'
                                    : 'Nouvelle fréquence'
                            }}</DialogTitle>
                        </DialogHeader>
                        <div class="grid gap-3 py-2">
                            <div>
                                <Label class="text-xs">Libellé *</Label>
                                <Input
                                    v-model="freqForm.designation"
                                    class="mt-1"
                                    placeholder="Ex. Très actif"
                                />
                            </div>
                            <div>
                                <Label class="text-xs"
                                    >Slug URL (optionnel, sinon dérivé du
                                    libellé)</Label
                                >
                                <Input
                                    v-model="freqForm.slug"
                                    class="mt-1 font-mono text-sm"
                                    placeholder="tres-actif"
                                />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <Label class="text-xs"
                                        >Commandes minimum</Label
                                    >
                                    <Input
                                        v-model="freqForm.commandes_minimum"
                                        class="mt-1"
                                        inputmode="numeric"
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs"
                                        >Commandes maximum (vide =
                                        illimité)</Label
                                    >
                                    <Input
                                        v-model="freqForm.commandes_maximum"
                                        class="mt-1"
                                        inputmode="numeric"
                                    />
                                </div>
                            </div>
                            <div>
                                <Label class="text-xs"
                                    >Intervalle max entre commandes
                                    (jours)</Label
                                >
                                <Input
                                    v-model="freqForm.intervalle_max_jours"
                                    class="mt-1"
                                    inputmode="numeric"
                                    placeholder="Ex. 30 — moyenne ≤ 30 jours"
                                />
                                <p
                                    class="mt-1 text-[11px] text-muted-foreground"
                                >
                                    Laisser vide pour ne pas utiliser l’écart
                                    entre commandes. Au moins 2 commandes datées
                                    sont nécessaires pour calculer la moyenne.
                                </p>
                            </div>
                            <div>
                                <Label class="text-xs"
                                    >Priorité (plus haut = appliqué en premier
                                    sur la carte client)</Label
                                >
                                <Input
                                    v-model="freqForm.priorite"
                                    class="mt-1"
                                    inputmode="numeric"
                                />
                            </div>
                        </div>
                        <DialogFooter class="gap-2">
                            <Button
                                variant="outline"
                                type="button"
                                @click="closeFreqModal"
                                >Annuler</Button
                            >
                            <Button
                                type="button"
                                class="bg-violet-600 text-white hover:bg-violet-700"
                                :disabled="
                                    !freqForm.designation.trim() || enCours
                                "
                                @click="submitFreq"
                            >
                                Enregistrer
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </section>

            <!-- ══════════════════ RELANCE COMMANDES ══════════════════ -->
            <section
                v-if="ongletActif === 'relanceCommande'"
                class="rounded-xl border border-gray-200 bg-white overflow-hidden"
            >
                <div class="border-b bg-gray-50 px-5 py-3">
                    <h2
                        class="font-semibold text-gray-700 flex items-center gap-2"
                    >
                        <Timer class="h-4 w-4 text-amber-600" /> Relance après
                        annulation
                    </h2>
                </div>
                <div
                    class="space-y-4 border-b border-amber-100 bg-amber-50/40 px-5 py-4"
                >
                    <p class="text-sm leading-relaxed text-gray-600">
                        Lorsqu’un agent
                        <span class="font-semibold">relance</span> une commande
                        annulée (réutilisation de l’ordonnance), la
                        <span class="font-semibold">pharmacie d’origine</span>
                        ne peut pas être sélectionnée pendant le nombre d’heures
                        indiqué (calcul à partir de la dernière mise à jour de
                        la commande annulée). Mettre
                        <span class="font-semibold">0</span> pour désactiver
                        cette contrainte.
                    </p>
                </div>
                <form
                    class="flex flex-wrap items-end gap-4 px-5 py-6"
                    @submit.prevent="sauverRelanceDelai"
                >
                    <div class="min-w-[200px] flex-1">
                        <label
                            class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-400"
                        >
                            Délai (heures) — même pharmacie
                        </label>
                        <input
                            v-model="
                                relanceDelaiForm.delai_relance_meme_pharmacie_heures
                            "
                            type="number"
                            min="0"
                            max="8760"
                            required
                            class="w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            0 = aucune restriction. Max. 8760 h (1 an).
                        </p>
                    </div>
                    <button
                        type="submit"
                        :disabled="enCours"
                        class="flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-60"
                    >
                        <Check class="h-4 w-4" /> Enregistrer
                    </button>
                </form>
            </section>

            <!-- ══════════════════ VÉRIFICATION ORDONNANCES (OCR + règles) ══════════════════ -->
            <section
                v-if="ongletActif === 'ordonnanceVerification'"
                class="rounded-xl border border-gray-200 bg-white overflow-hidden"
            >
                <div class="border-b bg-gray-50 px-5 py-3">
                    <h2
                        class="font-semibold text-gray-700 flex items-center gap-2"
                    >
                        <FileCheck class="h-4 w-4 text-teal-600" />
                        Contrôle automatique des ordonnances
                    </h2>
                </div>
                <div
                    class="space-y-3 border-b border-teal-100 bg-teal-50/40 px-5 py-4"
                >
                    <p class="text-sm leading-relaxed text-gray-600">
                        Après chaque envoi, le texte est extrait du
                        <span class="font-semibold">PDF</span> (texte intégré) ou
                        reconnu sur les
                        <span class="font-semibold">images</span> avec
                        <span class="font-semibold">Tesseract</span> (voir
                        ci‑dessous). L’analyse peut tourner en
                        <span class="font-semibold">file d’attente</span> ou
                        <span class="font-semibold">pendant l’envoi</span> selon le
                        mode choisi. Un
                        <span class="font-semibold">score</span> est calculé à
                        partir des pondérations puis comparé aux seuils. Les
                        listes de mots-clés sont cherchées dans le texte brut, sans
                        tenir compte des majuscules.
                    </p>
                </div>
                <form
                    class="space-y-6 px-5 py-6"
                    @submit.prevent="sauverOrdonnanceVerification"
                >
                    <label
                        class="flex cursor-pointer items-center gap-3 text-sm font-medium text-gray-800"
                    >
                        <input
                            v-model="ovForm.enabled"
                            type="checkbox"
                            class="size-4 rounded border-gray-300"
                        />
                        Activer le contrôle automatique
                    </label>

                    <div class="max-w-xl">
                        <label
                            class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-400"
                            >Quand lancer l’analyse</label
                        >
                        <select
                            v-model="ovForm.execution_mode"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800"
                        >
                            <option value="queue">
                                File d’attente (asynchrone)
                            </option>
                            <option value="immediate">
                                Immédiat — pendant l’upload
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            En mode immédiat, la réponse HTTP attend la fin de
                            l’analyse : prévoir un délai d’attente serveur
                            suffisant pour l’OCR.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-400"
                                >Ancienneté max. de la prescription (jours)</label
                            >
                            <input
                                v-model.number="ovForm.max_prescription_age_days"
                                type="number"
                                min="1"
                                max="3650"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-400"
                                >Score minimum pour « conforme » (%)</label
                            >
                            <input
                                v-model.number="ovForm.threshold_pass"
                                type="number"
                                min="0"
                                max="100"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-400"
                                >Score minimum pour « revue » (%)</label
                            >
                            <input
                                v-model.number="ovForm.threshold_review"
                                type="number"
                                min="0"
                                max="100"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <label
                        class="flex cursor-pointer items-center gap-3 text-sm text-gray-700"
                    >
                        <input
                            v-model="ovForm.block_pass_on_duplicate"
                            type="checkbox"
                            class="size-4 rounded border-gray-300"
                        />
                        Mettre en « revue » si le même fichier a déjà été déposé
                        (empreinte SHA-256 identique)
                    </label>

                    <div>
                        <label
                            class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-400"
                            >Commande Tesseract (OCR sur images)</label
                        >
                        <input
                            v-model="ovForm.tesseract_binary"
                            type="text"
                            class="w-full max-w-md rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono"
                            placeholder="tesseract"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Indiquez « tesseract » si l’exécutable est dans le PATH
                            du serveur ; sous Windows, utilisez le chemin complet si
                            besoin. Les PDF avec texte intégré sont lus sans OCR.
                        </p>
                    </div>

                    <div>
                        <p
                            class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400"
                        >
                            Pondération de chaque critère (points)
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="r in OV_RULE_KEYS"
                                :key="r.key"
                                class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 bg-gray-50/80 px-3 py-2"
                            >
                                <span class="text-xs text-gray-700">{{
                                    r.label
                                }}</span>
                                <input
                                    v-model.number="ovForm.rule_weights[r.key]"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-20 rounded border border-gray-300 px-2 py-1 text-sm text-right"
                                />
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Le score affiché est le pourcentage des points obtenus
                            par rapport à la somme des pondérations strictement
                            positives.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-1 lg:grid-cols-3">
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-400"
                                >Liste prescripteur / structure (1 mot-clé par
                                ligne)</label
                            >
                            <textarea
                                v-model="ovForm.kw_prescriber"
                                rows="6"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-xs"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-400"
                                >Liste patient (1 mot-clé par ligne)</label
                            >
                            <textarea
                                v-model="ovForm.kw_patient"
                                rows="6"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-xs"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-400"
                                >Liste médicament / posologie (1 mot-clé par
                                ligne)</label
                            >
                            <textarea
                                v-model="ovForm.kw_medicament"
                                rows="6"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-xs"
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="enCours"
                        class="flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-60"
                    >
                        <Check class="h-4 w-4" /> Enregistrer
                    </button>
                </form>
            </section>

            <!-- ══════════════════ MOTIFS D'ANNULATION ══════════════════ -->
            <section
                v-if="ongletActif === 'motifsAnnulation'"
                class="rounded-xl border border-gray-200 bg-white overflow-hidden"
            >
                <div
                    class="border-b bg-gray-50 px-5 py-3 flex items-center justify-between"
                >
                    <h2
                        class="font-semibold text-gray-700 flex items-center gap-2"
                    >
                        <RefreshCw class="h-4 w-4 text-blue-600" /> Motifs
                        d'annulation
                    </h2>
                    <span class="text-xs text-gray-400"
                        >{{ motifsAnnulation.length }} motif(s)</span
                    >
                </div>
                <div class="border-b px-5 py-4 bg-blue-50/40">
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Le <span class="font-semibold">code</span> (ex.
                        <code class="rounded bg-white/80 px-1 text-xs"
                            >medicaments_indisponibles</code
                        >) est stocké sur les commandes : ne le changez que si
                        aucune commande ne l’utilise encore. La
                        <span class="font-semibold">relance</span> contrôle
                        l’affichage du bouton « Relancer la commande » après
                        annulation.
                    </p>
                </div>

                <form
                    @submit.prevent="ajouterMotif"
                    class="border-b px-5 py-4 bg-blue-50/30"
                >
                    <p
                        class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400"
                    >
                        Nouveau motif
                    </p>
                    <div
                        class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end"
                    >
                        <div class="md:col-span-3">
                            <label class="mb-1 block text-xs text-gray-500"
                                >Code *
                                <span class="font-normal"
                                    >(a-z, chiffres, _)</span
                                ></label
                            >
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
                            <label class="mb-1 block text-xs text-gray-500"
                                >Libellé affiché *</label
                            >
                            <input
                                v-model="motifForm.label"
                                required
                                maxlength="255"
                                placeholder="Ex. : Rupture de stock"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs text-gray-500"
                                >Ordre</label
                            >
                            <input
                                v-model="motifForm.sort_order"
                                type="number"
                                min="0"
                                placeholder="auto"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                            />
                        </div>
                        <div class="md:col-span-2 flex items-center gap-2 pb-2">
                            <input
                                id="motif-new-relance"
                                v-model="motifForm.autorise_relance"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-blue-600"
                            />
                            <label
                                for="motif-new-relance"
                                class="text-sm text-gray-700"
                                >Relance autorisée</label
                            >
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
                            <th class="px-5 py-3 text-left font-medium">
                                Libellé
                            </th>
                            <th class="px-5 py-3 text-left font-medium">
                                Code
                            </th>
                            <th class="px-5 py-3 text-center font-medium w-20">
                                Ordre
                            </th>
                            <th class="px-5 py-3 text-center font-medium w-28">
                                Relance
                            </th>
                            <th class="px-5 py-3 text-center font-medium w-24">
                                Cmd.
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!motifsAnnulation.length">
                            <td
                                colspan="6"
                                class="px-5 py-6 text-center text-gray-400"
                            >
                                Aucun motif.
                            </td>
                        </tr>
                        <tr
                            v-for="m in motifsAnnulation"
                            :key="m.id"
                            class="hover:bg-gray-50/50"
                        >
                            <td class="px-5 py-3">
                                <input
                                    v-if="editingId === m.id"
                                    v-model="motifEdit.label"
                                    maxlength="255"
                                    class="w-full rounded border border-gray-300 px-2 py-1 text-sm"
                                />
                                <span
                                    v-else
                                    class="font-medium text-gray-800"
                                    >{{ m.label }}</span
                                >
                            </td>
                            <td class="px-5 py-3">
                                <input
                                    v-if="editingId === m.id"
                                    v-model="motifEdit.slug"
                                    :disabled="m.commandes_count > 0"
                                    maxlength="100"
                                    class="w-full rounded border border-gray-300 px-2 py-1 font-mono text-xs disabled:bg-gray-100 disabled:text-gray-500"
                                />
                                <span
                                    v-else
                                    class="font-mono text-xs text-gray-600"
                                    >{{ m.slug }}</span
                                >
                                <p
                                    v-if="
                                        editingId === m.id &&
                                        m.commandes_count > 0
                                    "
                                    class="mt-1 text-[11px] text-amber-700"
                                >
                                    Code verrouillé ({{ m.commandes_count }}
                                    commande(s)).
                                </p>
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
                                <span
                                    v-else
                                    class="text-xs font-medium"
                                    :class="
                                        m.autorise_relance
                                            ? 'text-green-700'
                                            : 'text-gray-400'
                                    "
                                >
                                    {{ m.autorise_relance ? 'Oui' : 'Non' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
                                    >{{ m.commandes_count }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="editingId === m.id">
                                    <button
                                        type="button"
                                        @click="sauvegarderMotif(m.id)"
                                        :disabled="enCours"
                                        class="mr-2 text-green-600 hover:text-green-800"
                                    >
                                        <Check class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="cancelEdit"
                                        class="text-gray-400 hover:text-gray-600"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        type="button"
                                        @click="ouvrirEditMotif(m)"
                                        class="mr-3 text-blue-500 hover:text-blue-700"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="supprimerMotif(m.id)"
                                        :disabled="m.commandes_count > 0"
                                        :class="
                                            m.commandes_count > 0
                                                ? 'cursor-not-allowed text-gray-300'
                                                : 'text-red-400 hover:text-red-600'
                                        "
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
