<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Search,
    Filter,
    Check,
    Package,
    Pill,
    XCircle,
    CheckCircle2,
    Merge,
    AlertCircle,
    CheckCheck,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type MedicamentInGroup = {
    id: number;
    designation: string;
    dosage?: string | null;
    forme?: string | null;
    type?: string | null;
    pu?: number | null;
    ventes: number;
    ca: number;
    created_at?: string;
    is_principal: boolean;
};

type Groupe = {
    id: number;
    numero: string;
    statut: 'en_attente' | 'verifie' | 'fusionne' | 'ignore';
    criteres: string[];
    medicaments: MedicamentInGroup[];
    total_si_fusion?: { ventes: number; ca: number };
};

const props = withDefaults(
    defineProps<{
        groupes?: Groupe[];
        stats?: {
            en_attente: number;
            verifies: number;
            fusionnes: number;
            total_produits: number;
        };
        filters?: {
            search?: string;
            statut?: string;
            tri?: string;
            critere?: string;
            criteres?: string[];
        };
        criteresDisponibles?: Record<string, string>;
    }>(),
    {
        groupes: () => [],
        stats: () => ({
            en_attente: 0,
            verifies: 0,
            fusionnes: 0,
            total_produits: 0,
        }),
        filters: () => ({}),
        criteresDisponibles: () => ({
            designation_similaire: 'Désignation similaire',
            dosage_identique: 'Dosage identique',
            forme_identique: 'Forme identique',
        }),
    },
);

const page = usePage();
const flashSuccess = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);
const flashError = computed(
    () => (page.props.flash as { error?: string } | undefined)?.error,
);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Médicaments', href: '/medicaments' },
    { title: 'Gestion des doublons', href: '/medicaments/doublons' },
];

const searchQuery = ref(props.filters.search ?? '');
const modalFusionOpen = ref(false);
const groupeEnFusion = ref<Groupe | null>(null);
const principalIdChoisi = ref<number | null>(null);
const showIgnorerModal = ref(false);
const groupeToIgnorer = ref<Groupe | null>(null);
const criteresActifs = ref<string[]>(
    Array.isArray(props.filters.criteres) && props.filters.criteres.length > 0
        ? props.filters.criteres
        : Object.keys(props.criteresDisponibles ?? {}).length > 0
          ? Object.keys(props.criteresDisponibles ?? {})
          : ['designation_similaire', 'dosage_identique', 'forme_identique'],
);

watch(
    () => props.filters.search,
    (v) => {
        searchQuery.value = v ?? '';
    },
);
watch(
    () => props.filters.criteres,
    (v) => {
        if (Array.isArray(v) && v.length > 0) criteresActifs.value = v;
    },
    { immediate: true },
);

function filtrer(key: string, value: string | string[]) {
    const payload: Record<string, string | string[] | undefined> = {
        ...props.filters,
    };
    if (key === 'criteres' && Array.isArray(value)) {
        payload.criteres = value;
    } else {
        payload[key] = typeof value === 'string' ? value || undefined : value;
    }
    router.get('/medicaments/doublons', payload, { preserveState: true });
}

function toggleCritere(cle: string) {
    const next = criteresActifs.value.includes(cle)
        ? criteresActifs.value.filter((c) => c !== cle)
        : [...criteresActifs.value, cle];
    if (next.length === 0) return;
    criteresActifs.value = next;
    filtrer('criteres', next);
}

function designationComplete(m: MedicamentInGroup) {
    return [m.designation, m.dosage].filter(Boolean).join(' ');
}

function ouvrirModalFusion(g: Groupe) {
    if (g.statut === 'fusionne') return;
    groupeEnFusion.value = g;
    const suggere =
        g.medicaments.find((m) => m.is_principal) ?? g.medicaments[0];
    principalIdChoisi.value = suggere?.id ?? null;
    modalFusionOpen.value = true;
}

function fermerModalFusion() {
    modalFusionOpen.value = false;
    groupeEnFusion.value = null;
    principalIdChoisi.value = null;
}

function confirmerFusion() {
    const g = groupeEnFusion.value;
    const pid = principalIdChoisi.value;
    if (!g || !pid) return;
    router.patch(
        `/medicaments/doublons/${g.id}/fusionner`,
        { principal_id: pid },
        { preserveScroll: true },
    );
    fermerModalFusion();
}

function openIgnorerModal(g: Groupe) {
    groupeToIgnorer.value = g;
    showIgnorerModal.value = true;
}

function confirmIgnorer() {
    if (!groupeToIgnorer.value) return;
    router.patch(
        `/medicaments/doublons/${groupeToIgnorer.value.id}/ignorer`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showIgnorerModal.value = false;
                groupeToIgnorer.value = null;
            },
        },
    );
}

function verifier(g: Groupe) {
    router.patch(
        `/medicaments/doublons/${g.id}/verifier`,
        {},
        { preserveScroll: true },
    );
}

const peutFusionner = computed(() => principalIdChoisi.value != null);

const statutBadgeClasses: Record<string, string> = {
    en_attente: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50',
    verifie: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50',
    fusionne: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50',
    ignore: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
};

const statutLabels: Record<string, string> = {
    en_attente: 'En Attente',
    verifie: 'Vérifié',
    fusionne: 'Fusionné',
    ignore: 'Ignoré',
};
</script>

<template>
    <Head title="Gestion des doublons médicaments - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="relative flex min-h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6 md:p-8"
        >
            <div
                v-if="flashSuccess"
                class="rounded-lg bg-emerald-100 py-3 px-4 text-sm font-medium text-emerald-800 dark:bg-emerald-900/50"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                class="rounded-lg bg-red-100 py-3 px-4 text-sm font-medium text-red-800 dark:bg-red-900/50"
            >
                {{ flashError }}
            </div>

            <!-- Tabs -->
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex gap-2">
                    <Link
                        href="/medicaments"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-white/75 transition-colors hover:bg-white/20 hover:text-white"
                    >
                        Catalogue médicaments
                    </Link>
                    <Link
                        :href="`/medicaments?onglet=db_medicament`"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-white/75 transition-colors hover:bg-white/20 hover:text-white"
                    >
                        DB médicament
                    </Link>
                    <button
                        class="rounded-lg px-4 py-2 text-sm font-medium bg-white/25 text-white backdrop-blur-sm"
                    >
                        Gestion des doublons
                    </button>
                    <Link
                        href="/medicaments"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-white/75 transition-colors hover:bg-white/20 hover:text-white"
                    >
                        Statistiques
                    </Link>
                </div>
            </div>

            <p
                class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-2 text-xs text-sky-800 dark:border-sky-800/50 dark:bg-sky-900/20 dark:text-sky-200"
            >
                <strong>Basé sur les commandes :</strong> Les doublons sont
                détectés parmi les médicaments (produits) présents dans les
                commandes, comme pour les clients.
            </p>

            <!-- Critères de détection -->
            <div
                class="rounded-xl border border-white/80 bg-white p-4 dark:border-white/10 dark:bg-white/95"
            >
                <p class="mb-2 text-sm font-medium text-foreground">
                    Critères de détection des doublons
                </p>
                <p class="mb-3 text-xs text-muted-foreground">
                    Cochez les critères utilisés pour regrouper les médicaments
                    similaires. Au moins un doit rester actif.
                </p>
                <div class="flex flex-wrap gap-3">
                    <label
                        v-for="(label, cle) in criteresDisponibles || {}"
                        :key="cle"
                        class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 transition-colors hover:bg-muted/50"
                        :class="
                            criteresActifs.includes(cle)
                                ? 'border-[#459cd1] bg-[#459cd1]/10'
                                : 'border-input'
                        "
                    >
                        <input
                            type="checkbox"
                            :checked="criteresActifs.includes(cle)"
                            class="size-4 rounded"
                            @change="toggleCritere(cle)"
                        />
                        <span class="text-sm">{{ label }}</span>
                    </label>
                </div>
            </div>

            <!-- Search & Filters -->
            <form
                class="flex flex-wrap items-center gap-4"
                @submit.prevent="filtrer('search', searchQuery)"
            >
                <div class="relative flex-1 min-w-[200px]">
                    <Search
                        class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Rechercher par désignation, dosage, forme..."
                        class="pl-9"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Filter class="size-4 text-muted-foreground" />
                    <select
                        :value="filters.tri"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="
                            (e: Event) =>
                                filtrer(
                                    'tri',
                                    (e.target as HTMLSelectElement).value,
                                )
                        "
                    >
                        <option value="">Trier par</option>
                        <option value="recent">Récent</option>
                        <option value="ventes">Ventes</option>
                        <option value="ca">CA</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <select
                        :value="filters.critere"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="
                            (e: Event) =>
                                filtrer(
                                    'critere',
                                    (e.target as HTMLSelectElement).value,
                                )
                        "
                    >
                        <option value="">Tous les critères</option>
                        <option
                            v-for="(label, cle) in criteresDisponibles || {}"
                            :key="cle"
                            :value="cle"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <Check class="size-4 text-muted-foreground" />
                    <select
                        :value="filters.statut"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="
                            (e: Event) =>
                                filtrer(
                                    'statut',
                                    (e.target as HTMLSelectElement).value,
                                )
                        "
                    >
                        <option value="">Tous les statuts</option>
                        <option value="en_attente">En Attente</option>
                        <option value="verifie">Vérifiés</option>
                        <option value="fusionne">Fusionnés</option>
                        <option value="ignore">Ignorés</option>
                    </select>
                </div>
                <Button type="submit">Rechercher</Button>
            </form>

            <!-- Stats cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div
                    class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/50 dark:bg-amber-900/20"
                >
                    <p
                        class="text-3xl font-bold text-amber-700 dark:text-amber-400"
                    >
                        {{ stats.en_attente }}
                    </p>
                    <p class="text-sm text-amber-800 dark:text-amber-300">
                        Groupes à traiter
                    </p>
                </div>
                <div
                    class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800/50 dark:bg-blue-900/20"
                >
                    <p
                        class="text-3xl font-bold text-blue-700 dark:text-blue-400"
                    >
                        {{ stats.verifies }}
                    </p>
                    <p class="text-sm text-blue-800 dark:text-blue-300">
                        Groupes vérifiés
                    </p>
                </div>
                <div
                    class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800/50 dark:bg-emerald-900/20"
                >
                    <p
                        class="text-3xl font-bold text-emerald-700 dark:text-emerald-400"
                    >
                        {{ stats.fusionnes }}
                    </p>
                    <p class="text-sm text-emerald-800 dark:text-emerald-300">
                        Fusions effectuées
                    </p>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-white/5"
                >
                    <p
                        class="text-3xl font-bold text-slate-800 dark:text-slate-200"
                    >
                        {{ stats.total_produits }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Produits concernés
                    </p>
                </div>
            </div>

            <!-- Groupes list -->
            <div class="space-y-6">
                <div
                    v-for="g in groupes"
                    :key="g.id"
                    class="rounded-xl border border-white/80 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/95"
                >
                    <div
                        class="mb-4 flex flex-wrap items-start justify-between gap-4"
                    >
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">
                                Groupe de doublons #{{ g.numero }}
                            </h3>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        statutBadgeClasses[g.statut] ||
                                            'bg-slate-100',
                                    ]"
                                >
                                    {{ statutLabels[g.statut] || g.statut }}
                                </span>
                                <span
                                    v-for="crit in g.criteres"
                                    :key="crit"
                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-700"
                                >
                                    {{ crit }}
                                </span>
                            </div>
                        </div>
                        <div
                            v-if="g.statut !== 'fusionne'"
                            class="rounded-lg border border-[#459cd1]/30 bg-sky-50/50 px-4 py-2 dark:bg-sky-900/20"
                        >
                            <p class="text-xs text-muted-foreground">
                                Total si fusion
                            </p>
                            <p class="font-semibold text-[#459cd1]">
                                {{ g.total_si_fusion?.ventes ?? 0 }} ventes ·
                                {{
                                    Number(
                                        g.total_si_fusion?.ca ?? 0,
                                    ).toLocaleString('fr-FR')
                                }}
                                xaf
                            </p>
                        </div>
                        <div
                            v-else
                            class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 dark:bg-emerald-900/20"
                        >
                            <p class="text-xs text-muted-foreground">
                                Total si fusion
                            </p>
                            <p
                                class="font-semibold text-emerald-700 dark:text-emerald-400"
                            >
                                {{ g.total_si_fusion?.ventes ?? 0 }} ventes ·
                                {{
                                    Number(
                                        g.total_si_fusion?.ca ?? 0,
                                    ).toLocaleString('fr-FR')
                                }}
                                xaf
                            </p>
                        </div>
                    </div>

                    <div class="mb-4 grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="m in g.medicaments"
                            :key="m.id"
                            class="rounded-lg border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
                        >
                            <div class="mb-2 flex items-center gap-2">
                                <Pill
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                <h4 class="font-semibold text-foreground">
                                    {{ designationComplete(m) }}
                                </h4>
                                <span
                                    v-if="m.is_principal"
                                    class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-600"
                                >
                                    Principal suggéré
                                </span>
                            </div>
                            <p class="mb-1 text-sm text-muted-foreground">
                                Forme : {{ m.forme || '—' }} · Type :
                                {{ m.type || '—' }}
                            </p>
                            <p
                                class="mb-1 text-sm font-medium text-emerald-600"
                            >
                                {{ m.ventes }} ventes ·
                                {{ Number(m.ca).toLocaleString('fr-FR') }} xaf
                            </p>
                            <p class="text-xs text-muted-foreground">
                                PU :
                                {{
                                    m.pu != null
                                        ? Number(m.pu).toLocaleString('fr-FR')
                                        : '—'
                                }}
                                xaf · Créé le {{ m.created_at || '—' }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="g.statut === 'fusionne'"
                        class="rounded-lg bg-emerald-100 py-3 px-4 text-center text-sm font-medium text-emerald-800 dark:bg-emerald-900/50"
                    >
                        Doublons fusionnés avec succès
                    </div>
                    <div
                        v-else-if="g.statut === 'ignore'"
                        class="rounded-lg bg-slate-200 py-3 px-4 text-center text-sm font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                    >
                        Groupe ignoré – ces médicaments sont considérés comme
                        distincts
                    </div>
                    <div
                        v-else-if="g.statut === 'verifie'"
                        class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800/50 dark:bg-blue-900/20"
                    >
                        <p class="text-sm text-blue-800 dark:text-blue-300">
                            Vérifié – Aucune action nécessaire pour le moment
                        </p>
                        <Button
                            class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]"
                            @click="ouvrirModalFusion(g)"
                        >
                            <Merge class="mr-2 size-4" />
                            Fusionner quand même
                        </Button>
                    </div>
                    <div
                        v-else
                        class="flex flex-wrap items-center justify-end gap-2"
                    >
                        <Button
                            variant="outline"
                            class="border-slate-300"
                            @click="openIgnorerModal(g)"
                        >
                            <XCircle class="mr-2 size-4" />
                            Ignorer
                        </Button>
                        <Button
                            variant="outline"
                            class="border-blue-300 text-blue-700 hover:bg-blue-50"
                            @click="verifier(g)"
                        >
                            <CheckCircle2 class="mr-2 size-4" />
                            Marquer comme vérifié
                        </Button>
                        <Button
                            class="bg-emerald-600 text-white hover:bg-emerald-700"
                            @click="ouvrirModalFusion(g)"
                        >
                            <Merge class="mr-2 size-4" />
                            Fusionner les médicaments
                        </Button>
                    </div>
                </div>

                <div
                    v-if="!groupes.length"
                    class="rounded-xl border border-dashed bg-white/50 py-12 text-center text-muted-foreground dark:border-white/10"
                >
                    Aucun groupe de doublons trouvé.
                </div>
            </div>
        </div>

        <!-- Modal Fusion : choix dynamique du médicament à conserver -->
        <Dialog :open="modalFusionOpen" @update:open="modalFusionOpen = $event">
            <DialogContent
                class="!flex max-h-[min(85vh,600px)] !gap-0 flex-col overflow-hidden p-0 sm:max-w-xl"
                :show-close-button="true"
            >
                <DialogHeader
                    class="shrink-0 border-b px-4 py-3 pr-12 text-left sm:px-5"
                >
                    <DialogTitle
                        class="flex items-center gap-2 text-base font-semibold sm:text-lg"
                    >
                        <Package
                            class="size-5 shrink-0 text-[#459cd1] sm:size-6"
                        />
                        Fusion des médicaments doublons
                    </DialogTitle>
                </DialogHeader>

                <div
                    class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-4 py-3 sm:px-5"
                >
                    <div
                        class="flex gap-2 rounded-lg border border-amber-300 bg-amber-50 p-2.5 dark:border-amber-700 dark:bg-amber-900/20"
                    >
                        <AlertCircle
                            class="size-4 shrink-0 text-amber-600 dark:text-amber-400 sm:size-5"
                        />
                        <div class="text-xs leading-snug sm:text-sm">
                            <p
                                class="font-semibold text-amber-800 dark:text-amber-200"
                            >
                                Attention : Action irréversible
                            </p>
                            <p
                                class="mt-0.5 text-amber-700 dark:text-amber-300"
                            >
                                Un seul médicament sera conservé. Les autres
                                seront supprimés de la base. Choisissez celui à
                                conserver.
                            </p>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-3 text-sm font-semibold text-foreground">
                            Quel médicament souhaitez-vous conserver ?
                        </h4>
                        <div class="space-y-2">
                            <label
                                v-for="m in groupeEnFusion?.medicaments ?? []"
                                :key="m.id"
                                class="flex cursor-pointer items-start gap-3 rounded-lg border-2 p-3 transition-colors"
                                :class="
                                    principalIdChoisi === m.id
                                        ? 'border-[#459cd1] bg-[#459cd1]/10'
                                        : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'
                                "
                            >
                                <input
                                    v-model="principalIdChoisi"
                                    type="radio"
                                    :value="m.id"
                                    name="principal_medicament"
                                    class="mt-1 size-4"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-foreground">
                                        {{ designationComplete(m) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ m.forme || '—' }} ·
                                        {{ m.ventes }} ventes ·
                                        {{
                                            Number(m.ca).toLocaleString('fr-FR')
                                        }}
                                        xaf
                                    </p>
                                </div>
                                <CheckCheck
                                    v-if="principalIdChoisi === m.id"
                                    class="size-5 shrink-0 text-[#459cd1]"
                                />
                            </label>
                        </div>
                    </div>

                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-700 dark:bg-slate-800/50"
                    >
                        <p class="text-xs text-muted-foreground">
                            <strong>{{
                                (groupeEnFusion?.medicaments?.length ?? 1) - 1
                            }}</strong>
                            médicament(s) seront supprimés.
                        </p>
                    </div>
                </div>

                <DialogFooter
                    class="shrink-0 flex-row justify-between gap-2 border-t px-4 py-3 sm:justify-between sm:px-5"
                >
                    <Button
                        variant="destructive"
                        class="h-9 bg-red-600 px-4 text-sm text-white hover:bg-red-700"
                        @click="fermerModalFusion"
                    >
                        Annuler
                    </Button>
                    <Button
                        class="h-9 bg-[#459cd1] px-4 text-sm text-white hover:bg-[#3a8ab8]"
                        :disabled="!peutFusionner"
                        @click="confirmerFusion"
                    >
                        Fusionner (conserver le médicament sélectionné)
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmModal
            :open="showIgnorerModal"
            title="Marquer comme ignoré"
            :description="
                groupeToIgnorer
                    ? `Marquer le groupe #${groupeToIgnorer.numero} comme ignoré ? Ces médicaments seront considérés comme distincts.`
                    : ''
            "
            confirm-text="Ignorer"
            variant="default"
            @update:open="showIgnorerModal = $event"
            @confirm="confirmIgnorer"
        />
    </AppLayout>
</template>
