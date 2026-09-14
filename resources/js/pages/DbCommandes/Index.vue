<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Database,
    Download,
    Link2,
    Plus,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, nextTick, ref, watch } from 'vue';
import { useCommandeModals } from '@/composables/useCommandeModals';
import CommandesTable from '@/components/commandes/CommandesTable.vue';
import CommandeStatusFilters from '@/components/commandes/CommandeStatusFilters.vue';
import FlashToastHost from '@/components/FlashToastHost.vue';
import ModuleEmptyState from '@/components/shared/ModuleEmptyState.vue';
import ModuleFilterPanel from '@/components/shared/ModuleFilterPanel.vue';
import ModulePagination from '@/components/shared/ModulePagination.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    moduleCardClass,
    moduleInputDateClass,
    modulePageClass,
    modulePageShellClass,
    modulePaginationWrapperClass,
    modulePrimaryButtonClass,
    moduleSelectClass,
} from '@/lib/bengadokUi';
import { showGlobalErrorToast } from '@/lib/globalToast';
import {
    getUploadLimits,
    validateFileSize,
} from '@/lib/uploadLimits';
import { dashboard } from '@/routes';
import { STATUTS_COMMANDE } from '@/types';
import type {
    BreadcrumbItem,
    CommandeListItem,
    MotifAnnulationOption,
} from '@/types';

const CommandeDetailDrawer = defineAsyncComponent(
    () => import('@/components/commandes/CommandeDetailDrawer.vue'),
);
const CommandeEnregistrementModal = defineAsyncComponent(
    () => import('@/components/commandes/CommandeEnregistrementModal.vue'),
);
const CommandeBulkAnnulerModal = defineAsyncComponent(
    () => import('@/components/commandes/CommandeBulkAnnulerModal.vue'),
);
const RecuCommandeModal = defineAsyncComponent(
    () => import('@/components/commandes/RecuCommandeModal.vue'),
);
type CommandeDetailDrawerExpose = {
    openDetail: (id: number) => void;
    closeDetail: () => void;
};

type DbImportRow = {
    id: number;
    code_commande?: string | null;
    date_commande?: string | null;
    nom_client?: string | null;
    telephone?: string | null;
    pharmacie?: string | null;
    medicaments?: string | null;
    total_paye_client?: number | null;
    statut_fichier?: string | null;
    statut_fichier_label?: string | null;
    integrated: boolean;
    integration_error?: string | null;
    commande_id?: number | null;
    commande_numero?: string | null;
};

const props = withDefaults(
    defineProps<{
        tab?: 'commandes' | 'imports';
        commandes?: {
            data: CommandeListItem[];
            links: Array<{ url: string | null; label: string; active: boolean }>;
            from?: number;
            to?: number;
            total?: number;
        };
        stats?: Record<string, number>;
        filters?: {
            search?: string;
            status?: string;
            periode?: string;
            date?: string;
        };
        imports?: {
            data: DbImportRow[];
            links: Array<{ url: string | null; label: string; active: boolean }>;
            from?: number;
            to?: number;
            total?: number;
        };
        importStats?: {
            total?: number;
            pending?: number;
            integrated?: number;
            failed?: number;
        };
        importFilters?: {
            import_search?: string;
            statut_fichier?: string;
            etat_integration?: string;
        };
        statutsFichierImport?: Record<string, string>;
        importMeta?: {
            templateUrl?: string;
            purgeConfirmationPhrase?: string;
        };
        referentiels?: Record<string, unknown>;
        canManageCommandes?: boolean;
        canDeleteCommandes?: boolean;
        canManageImports?: boolean;
        openDetailCommandeId?: number | null;
    }>(),
    {
        tab: 'commandes',
        commandes: () => ({ data: [], links: [], total: 0 }),
        stats: () => ({}),
        filters: () => ({}),
        imports: () => ({ data: [], links: [], total: 0 }),
        importStats: () => ({}),
        importFilters: () => ({}),
        statutsFichierImport: () => ({
            nouvelle: 'Nouvelle',
            en_attente: 'En attente',
            validee: 'Validée',
            retiree: 'Livrée',
            annulee: 'Annulée',
        }),
        importMeta: () => ({}),
        referentiels: () => ({}),
        canManageCommandes: false,
        canDeleteCommandes: false,
        canManageImports: false,
        openDetailCommandeId: null,
    },
);

const page = usePage();
const roles = computed(
    () =>
        (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? [],
);
const isAdmin = computed(() =>
    roles.value.some((r) => ['admin', 'super_admin'].includes(r)),
);
const canManageImports = computed(
    () => props.canManageImports ?? isAdmin.value,
);
const canManageCommandesRef = computed(() => props.canManageCommandes);
const canCreateCommande = computed(() => {
    if (props.canManageCommandes) {
        return true;
    }
    return roles.value.some((r) =>
        ['admin', 'super_admin', 'agent_call_center'].includes(r),
    );
});
const returnHubRef = computed(() => 'gestion' as const);

const motifsAnnulation = computed(
    () => (page.props.motifs_annulation ?? []) as MotifAnnulationOption[],
);
const motifsRelance = computed(() =>
    Object.fromEntries(
        motifsAnnulation.value.map((m) => [m.slug, m.autorise_relance]),
    ),
);
const motifOptions = computed(() =>
    motifsAnnulation.value.map((m) => ({
        key: m.slug,
        label: m.label,
        desc: m.autorise_relance
            ? 'Après annulation, un agent pourra relancer la commande (ex. autre pharmacie).'
            : 'Aucune relance proposée pour ce motif.',
    })),
);
const motifLabelBySlug = computed(() =>
    Object.fromEntries(motifsAnnulation.value.map((m) => [m.slug, m.label])),
);

const selectedCommandeIds = ref<Set<number>>(new Set());
const selectedImportIds = ref<Set<number>>(new Set());

function clearImportSelection() {
    selectedImportIds.value = new Set();
}

const detailDrawerRef = ref<CommandeDetailDrawerExpose | null>(null);
const heavyUiMounted = ref(!!props.openDetailCommandeId);

function mountHeavyUi(): void {
    heavyUiMounted.value = true;
}

const modals = useCommandeModals({
    canManageCommandes: canManageCommandesRef,
    selectedIds: selectedCommandeIds,
    clearSelection: () => {
        selectedCommandeIds.value = new Set();
    },
    onRelanceSuccess: () => detailDrawerRef.value?.closeDetail(),
    returnHub: returnHubRef,
});

function prefetchCommandeHeavyUi(): void {
    mountHeavyUi();
    modals.ensureReferentiels();
}

function openDetail(id: number) {
    mountHeavyUi();
    void nextTick(() => {
        detailDrawerRef.value?.openDetail(id);
    });
}

watch(
    () => [
        modals.showEnregistrementModal,
        modals.showRelancerModal,
        modals.showRecuModal,
        modals.showBulkAnnulerModal,
    ],
    (flags) => {
        if (flags.some(Boolean)) {
            mountHeavyUi();
        }
    },
);

watch(
    () => props.openDetailCommandeId,
    (id) => {
        if (id) {
            openDetail(id);
        }
    },
    { immediate: true },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Gestion commandes', href: '/db-commandes' },
];

const activeTab = computed(() => props.tab);
const searchQuery = ref(props.filters.search ?? '');
const importSearchQuery = ref(props.importFilters.import_search ?? '');
const importing = ref(false);
const importForm = ref<{ file: File | null; skip_duplicates: boolean }>({
    file: null,
    skip_duplicates: true,
});
const importFileInput = ref<HTMLInputElement | null>(null);
const importPurgeModalOpen = ref(false);
const importPurgeConfirmInput = ref('');

const importPurgePhrase = computed(
    () =>
        props.importMeta.purgeConfirmationPhrase ?? 'VIDER IMPORTS STAGING',
);
const canSubmitImportPurge = computed(
    () => importPurgeConfirmInput.value.trim() === importPurgePhrase.value,
);

function openImportPurgeModal() {
    importPurgeConfirmInput.value = '';
    importPurgeModalOpen.value = true;
}

function closeImportPurgeModal() {
    importPurgeModalOpen.value = false;
}

function submitImportPurgeAll() {
    if (!canSubmitImportPurge.value) return;
    router.post(
        '/db-commandes/purge-all',
        { confirmation: importPurgeConfirmInput.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                clearImportSelection();
                closeImportPurgeModal();
                importPurgeConfirmInput.value = '';
            },
        },
    );
}

watch(
    () => props.filters.search,
    (v) => {
        searchQuery.value = v ?? '';
    },
);
watch(
    () => props.importFilters.import_search,
    (v) => {
        importSearchQuery.value = v ?? '';
    },
);

function switchTab(tab: 'commandes' | 'imports') {
    router.get('/db-commandes', { tab }, { preserveState: true });
}

function filtrerCommandes(key: string, value: string) {
    const raw = { ...props.filters };
    const v = value.trim() || undefined;
    if (key === 'periode') {
        raw.periode = v;
        if (v) raw.date = undefined;
    } else if (key === 'date') {
        raw.date = v;
        if (v) raw.periode = undefined;
    } else {
        (raw as Record<string, string | undefined>)[key] = v;
    }
    router.get(
        '/db-commandes',
        {
            tab: 'commandes',
            ...(raw.search ? { search: raw.search } : {}),
            ...(raw.status ? { status: raw.status } : {}),
            ...(raw.periode ? { periode: raw.periode } : {}),
            ...(raw.date ? { date: raw.date } : {}),
        },
        { preserveState: true },
    );
}

function filtrerImports(key: string, value: string) {
    router.get(
        '/db-commandes',
        {
            tab: 'imports',
            ...props.importFilters,
            [key]: value || undefined,
        },
        { preserveState: true },
    );
}

const allCommandesSelected = computed(() => {
    const data = props.commandes?.data ?? [];
    return (
        data.length > 0 &&
        data.every((c) => selectedCommandeIds.value.has(c.id))
    );
});

function toggleAllCommandes() {
    const data = props.commandes?.data ?? [];
    const next = new Set(selectedCommandeIds.value);
    if (allCommandesSelected.value) data.forEach((c) => next.delete(c.id));
    else data.forEach((c) => next.add(c.id));
    selectedCommandeIds.value = next;
}

function toggleCommande(id: number) {
    const next = new Set(selectedCommandeIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedCommandeIds.value = next;
}

function exportUrl(): string {
    const params = new URLSearchParams();
    if (props.filters.search) params.set('search', props.filters.search);
    if (props.filters.status) params.set('status', props.filters.status);
    if (props.filters.periode) params.set('periode', props.filters.periode);
    if (props.filters.date) params.set('date', props.filters.date);
    const q = params.toString();
    return q ? `/db-commandes/export?${q}` : '/db-commandes/export';
}

function exportImportsUrl(): string {
    const params = new URLSearchParams();
    if (props.importFilters.import_search) {
        params.set('import_search', props.importFilters.import_search);
    }
    if (props.importFilters.statut_fichier) {
        params.set('statut_fichier', props.importFilters.statut_fichier);
    }
    if (props.importFilters.etat_integration) {
        params.set('etat_integration', props.importFilters.etat_integration);
    }
    const q = params.toString();
    return q
        ? `/db-commandes/export-imports?${q}`
        : '/db-commandes/export-imports';
}

function destroyCommandes(ids: number[]) {
    if (!ids.length) return;
    const label =
        ids.length === 1
            ? 'Supprimer cette commande ?'
            : `Supprimer ${ids.length} commande(s) ?`;
    if (!confirm(label)) return;
    router.post(
        '/db-commandes/commandes/destroy-bulk',
        { ids },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedCommandeIds.value = new Set();
            },
        },
    );
}

function destroySelectedCommandes() {
    destroyCommandes([...selectedCommandeIds.value]);
}

function destroyOneCommande(id: number) {
    destroyCommandes([id]);
}

function submitImport() {
    if (!importForm.value.file || importing.value) return;

    const sizeError = validateFileSize(importForm.value.file);
    if (sizeError) {
        showGlobalErrorToast(sizeError);
        return;
    }

    const formData = new FormData();
    formData.append('file', importForm.value.file);
    formData.append('skip_duplicates', importForm.value.skip_duplicates ? '1' : '0');
    importing.value = true;
    router.post('/db-commandes/import', formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.value = { file: null, skip_duplicates: true };
            if (importFileInput.value) importFileInput.value.value = '';
        },
        onFinish: () => {
            importing.value = false;
        },
    });
}

function integrateRow(id: number) {
    router.post(`/db-commandes/${id}/integrate`, {}, { preserveScroll: true });
}

function retryIntegrateRow(id: number) {
    integrateRow(id);
}

function integrateAllPending() {
    if (
        !confirm(
            `Intégrer ${props.importStats.pending ?? 0} commande(s) importée(s) dans le système ?`,
        )
    ) {
        return;
    }
    router.post('/db-commandes/integrate-all', {}, { preserveScroll: true });
}

const pendingImportRowsOnPage = computed(() =>
    (props.imports?.data ?? []).filter((r) => !r.integrated),
);

const allImportsSelected = computed(() => {
    const pending = pendingImportRowsOnPage.value;
    return (
        pending.length > 0 &&
        pending.every((r) => selectedImportIds.value.has(r.id))
    );
});

const someImportsSelectedOnPage = computed(() =>
    pendingImportRowsOnPage.value.some((r) =>
        selectedImportIds.value.has(r.id),
    ),
);

function setImportSelected(
    id: number,
    checked: boolean | 'indeterminate',
) {
    if (checked === 'indeterminate') {
        return;
    }
    const next = new Set(selectedImportIds.value);
    if (checked) {
        next.add(id);
    } else {
        next.delete(id);
    }
    selectedImportIds.value = next;
}

function setAllImportsSelected(checked: boolean | 'indeterminate') {
    const selectAll =
        checked === true || checked === 'indeterminate';
    const next = new Set(selectedImportIds.value);
    for (const row of pendingImportRowsOnPage.value) {
        if (selectAll) {
            next.add(row.id);
        } else {
            next.delete(row.id);
        }
    }
    selectedImportIds.value = next;
}

function integrateSelectedImports() {
    const ids = [...selectedImportIds.value];
    if (!ids.length) return;
    router.post('/db-commandes/integrate-bulk', { ids }, {
        preserveScroll: true,
        onSuccess: () => {
            clearImportSelection();
        },
    });
}

function destroySelectedImports() {
    const ids = [...selectedImportIds.value];
    if (!ids.length) return;
    if (!confirm(`Supprimer ${ids.length} ligne(s) importée(s) ?`)) return;
    router.post('/db-commandes/destroy-bulk', { ids }, {
        preserveScroll: true,
        onSuccess: () => {
            clearImportSelection();
        },
    });
}

function formatMoney(v: number | null | undefined): string {
    if (v == null || !Number.isFinite(v)) return '—';
    return Number(v).toLocaleString('fr-FR');
}

const templateUrl = computed(
    () => props.importMeta.templateUrl ?? '/db-commandes/modele',
);
const statuts = STATUTS_COMMANDE;
const uploadLimits = getUploadLimits();
</script>

<template>
    <Head title="Gestion commandes - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="modulePageClass">
            <div class="relative z-10 flex shrink-0 flex-wrap items-center gap-3">
                <div
                    class="flex size-9 items-center justify-center rounded-full bg-[#459cd1] text-white"
                >
                    <Database class="size-4" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">Gestion commandes</h1>
                    <p class="mt-0.5 max-w-2xl text-sm text-muted-foreground">
                        Saisie manuelle, import Excel, export et suivi global des
                        commandes système. Vous pouvez aussi gérer le flux
                        opérationnel depuis le module
                        <Link
                            href="/commandes"
                            class="font-medium text-[#459cd1] underline-offset-2 hover:underline"
                        >
                            Commandes
                        </Link>.
                    </p>
                </div>
            </div>

            <div :class="modulePageShellClass">
                <div
                    class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4"
                >
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-[#459cd1]/10 px-2.5 py-1 font-medium text-[#459cd1]">
                            {{ commandes.total ?? 0 }} commande(s) système
                        </span>
                        <span
                            v-if="canManageImports && (importStats.pending ?? 0) > 0"
                            class="rounded-full bg-amber-100 px-2.5 py-1 font-medium text-amber-900"
                        >
                            {{ importStats.pending }} à intégrer
                        </span>
                    </div>
                    <div
                        v-if="activeTab === 'commandes'"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <Button
                            v-if="canCreateCommande"
                            type="button"
                            :class="[modulePrimaryButtonClass, 'h-10 gap-2']"
                            @mouseenter="prefetchCommandeHeavyUi"
                            @focus="prefetchCommandeHeavyUi"
                            @click="modals.openEnregistrementModal()"
                        >
                            <Plus class="size-4" />
                            Nouvelle commande
                        </Button>
                        <Button variant="outline" class="h-10 gap-2" as-child>
                            <a :href="exportUrl()">
                                <Download class="size-4" />
                                Exporter Excel
                            </a>
                        </Button>
                    </div>
                </div>

                <div class="mb-4 flex gap-1 rounded-lg border border-border bg-muted/40 p-1">
                    <button
                        type="button"
                        class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors"
                        :class="
                            activeTab === 'commandes'
                                ? 'bg-white text-[#459cd1] shadow-sm dark:bg-card'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="switchTab('commandes')"
                    >
                        Toutes les commandes ({{ commandes.total ?? 0 }})
                    </button>
                    <button
                        v-if="canManageImports"
                        type="button"
                        class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors"
                        :class="
                            activeTab === 'imports'
                                ? 'bg-white text-[#459cd1] shadow-sm dark:bg-card'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="switchTab('imports')"
                    >
                        Import Excel ({{ importStats.pending ?? 0 }} à intégrer)
                    </button>
                </div>

                <!-- Toutes les commandes -->
                <template v-if="activeTab === 'commandes'">
                    <ModuleFilterPanel
                        v-model:search="searchQuery"
                        placeholder="N°, client, téléphone, médicament…"
                        show-submit
                        :counter="commandes.total ?? 0"
                        :counter-icon="Database"
                        counter-class="bg-[#459cd1]"
                        @submit="filtrerCommandes('search', searchQuery)"
                    >
                        <select
                            :value="filters.periode ?? ''"
                            :class="moduleSelectClass"
                            @change="
                                (e: Event) =>
                                    filtrerCommandes(
                                        'periode',
                                        (e.target as HTMLSelectElement).value,
                                    )
                            "
                        >
                            <option value="">Toutes les dates</option>
                            <option value="aujourdhui">Aujourd'hui</option>
                            <option value="semaine">Cette semaine</option>
                            <option value="mois">Ce mois</option>
                            <option value="annee">Cette année</option>
                            <option value="tout">Tout l'historique</option>
                        </select>
                        <input
                            :value="filters.date ?? ''"
                            type="date"
                            :class="moduleInputDateClass"
                            @input="
                                (e: Event) =>
                                    filtrerCommandes(
                                        'date',
                                        (e.target as HTMLInputElement).value,
                                    )
                            "
                        />
                    </ModuleFilterPanel>

                    <CommandeStatusFilters
                        :statuts="statuts"
                        :stats="stats"
                        :active-status="filters.status"
                        @filter="(s) => filtrerCommandes('status', s)"
                    />

                    <div :class="[moduleCardClass, 'overflow-hidden']">
                        <CommandesTable
                            :commandes="commandes"
                            :stats="stats"
                            :filters="filters"
                            :statuts="statuts"
                            :selected-ids="selectedCommandeIds"
                            :all-selected="allCommandesSelected"
                            :some-selected="selectedCommandeIds.size > 0"
                            :can-create-commande="canCreateCommande"
                            :can-delete-commandes="canDeleteCommandes"
                            :show-bulk-annuler="canManageCommandesRef"
                            :show-export-csv="false"
                            @toggle-all="toggleAllCommandes"
                            @toggle-one="toggleCommande"
                            @clear-selection="selectedCommandeIds = new Set()"
                            @delete-selected="destroySelectedCommandes"
                            @delete-one="destroyOneCommande"
                            @open-bulk-annuler-modal="modals.openBulkAnnulerModal()"
                            @open-detail="openDetail"
                        />
                    </div>
                </template>

                <!-- Import Excel : chargement + lignes à intégrer -->
                <template v-else-if="activeTab === 'imports' && canManageImports">
                    <div
                        :class="[moduleCardClass, 'mb-4 space-y-4 p-4 sm:p-5']"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="text-base font-semibold">Charger un fichier Excel</h2>
                            <div class="flex flex-wrap gap-2">
                                <Button variant="outline" class="h-10 gap-2" as-child>
                                    <a :href="exportImportsUrl()">
                                        <Download class="size-4" />
                                        Exporter Excel
                                    </a>
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    class="h-10 gap-2"
                                    @click="openImportPurgeModal"
                                >
                                    <Trash2 class="size-4" />
                                    Vider les imports
                                </Button>
                            </div>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Importez un fichier Excel (.xlsx, .xls) ou CSV à tout moment
                            (max. {{ uploadLimits.max_label }}).
                            Colonne « Statut » : mêmes valeurs que le module Commandes
                            (Nouvelle, En attente, Validée, Livrée, Annulée).
                        </p>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-[200px] flex-1 space-y-1">
                                <Label>Fichier (.xlsx, .xls, .csv)</Label>
                                <Input
                                    ref="importFileInput"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    @change="
                                        (e: Event) => {
                                            importForm.file =
                                                (e.target as HTMLInputElement).files?.[0] ?? null;
                                        }
                                    "
                                />
                            </div>
                            <Button variant="outline" class="gap-2 shrink-0" as-child>
                                <a :href="templateUrl">
                                    <Download class="size-4" />
                                    Modèle
                                </a>
                            </Button>
                            <Button
                                :class="[modulePrimaryButtonClass, 'gap-2 shrink-0']"
                                :disabled="!importForm.file || importing"
                                type="button"
                                @click="submitImport"
                            >
                                <Spinner v-if="importing" class="size-4" />
                                <Upload v-else class="size-4" />
                                {{ importing ? 'Chargement…' : 'Charger' }}
                            </Button>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-muted-foreground">
                            <input
                                v-model="importForm.skip_duplicates"
                                type="checkbox"
                                class="size-4 rounded border-input"
                            />
                            Ignorer les lignes déjà importées
                        </label>
                    </div>

                    <div
                        v-if="selectedImportIds.size > 0"
                        class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-[#459cd1]/30 bg-[#459cd1]/5 px-4 py-3 text-sm"
                    >
                        <span class="font-medium">
                            {{ selectedImportIds.size }} ligne(s) sélectionnée(s)
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="clearImportSelection"
                        >
                            Tout désélectionner
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="gap-2"
                            @click="integrateSelectedImports"
                        >
                            <Link2 class="size-4" />
                            Intégrer
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            class="gap-2"
                            @click="destroySelectedImports"
                        >
                            <Trash2 class="size-4" />
                            Supprimer
                        </Button>
                    </div>
                    <div
                        v-if="(importStats.pending ?? 0) > 0"
                        class="mb-4"
                    >
                        <Button
                            :class="[modulePrimaryButtonClass, 'gap-2']"
                            type="button"
                            @click="integrateAllPending"
                        >
                            <Link2 class="size-4" />
                            Intégrer toutes les lignes en attente ({{ importStats.pending }})
                        </Button>
                    </div>

                    <ModuleFilterPanel
                        v-model:search="importSearchQuery"
                        placeholder="Code, client, pharmacie…"
                        show-submit
                        :counter="imports.total ?? 0"
                        @submit="filtrerImports('import_search', importSearchQuery)"
                    >
                        <select
                            :value="importFilters.statut_fichier ?? ''"
                            :class="moduleSelectClass"
                            aria-label="Statut fichier"
                            @change="
                                (e: Event) =>
                                    filtrerImports(
                                        'statut_fichier',
                                        (e.target as HTMLSelectElement).value,
                                    )
                            "
                        >
                            <option value="">Tous les statuts fichier</option>
                            <option
                                v-for="(label, key) in statutsFichierImport"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <select
                            :value="importFilters.etat_integration ?? ''"
                            :class="moduleSelectClass"
                            aria-label="État d'intégration"
                            @change="
                                (e: Event) =>
                                    filtrerImports(
                                        'etat_integration',
                                        (e.target as HTMLSelectElement).value,
                                    )
                            "
                        >
                            <option value="">Tous les états d'intégration</option>
                            <option value="pending">À intégrer</option>
                            <option value="failed">Erreur d'intégration</option>
                            <option value="integrated">Déjà intégrées</option>
                        </select>
                    </ModuleFilterPanel>

                    <div
                        v-if="imports.data.length"
                        :class="[moduleCardClass, 'overflow-hidden']"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1000px] text-sm">
                                <thead class="border-b bg-muted/50">
                                    <tr>
                                        <th class="w-10 px-2 py-3 text-left">
                                            <Checkbox
                                                :model-value="
                                                    allImportsSelected
                                                        ? true
                                                        : someImportsSelectedOnPage
                                                          ? 'indeterminate'
                                                          : false
                                                "
                                                @update:model-value="setAllImportsSelected"
                                            />
                                        </th>
                                        <th class="px-3 py-3 text-left">Code</th>
                                        <th class="px-3 py-3 text-left">Client</th>
                                        <th class="px-3 py-3 text-left">Pharmacie</th>
                                        <th class="px-3 py-3 text-left">Médicaments</th>
                                        <th class="px-3 py-3 text-right">Total</th>
                                        <th class="px-3 py-3 text-left">Statut fichier</th>
                                        <th class="px-3 py-3 text-left">Intégration</th>
                                        <th class="px-3 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in imports.data"
                                        :key="row.id"
                                        class="border-b hover:bg-muted/30"
                                    >
                                        <td class="px-2 py-3">
                                            <Checkbox
                                                v-if="!row.integrated"
                                                :model-value="selectedImportIds.has(row.id)"
                                                @update:model-value="
                                                    (checked) =>
                                                        setImportSelected(row.id, checked)
                                                "
                                            />
                                        </td>
                                        <td class="px-3 py-3 font-mono text-xs">
                                            {{ row.code_commande || '—' }}
                                        </td>
                                        <td class="px-3 py-3">
                                            <div>{{ row.nom_client || '—' }}</div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ row.telephone || '—' }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">{{ row.pharmacie || '—' }}</td>
                                        <td class="max-w-[200px] truncate px-3 py-3">
                                            {{ row.medicaments || '—' }}
                                        </td>
                                        <td class="px-3 py-3 text-right tabular-nums">
                                            {{ formatMoney(row.total_paye_client) }}
                                        </td>
                                        <td class="px-3 py-3">
                                            <span
                                                class="rounded-full bg-muted px-2 py-0.5 text-xs text-foreground"
                                            >
                                                {{ row.statut_fichier_label || 'Non renseigné' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span
                                                v-if="row.integrated"
                                                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800"
                                            >
                                                <CheckCircle2 class="size-3.5" />
                                                {{ row.commande_numero }}
                                            </span>
                                            <div
                                                v-else-if="row.integration_error"
                                                class="max-w-[220px] space-y-1"
                                            >
                                                <span class="text-xs font-medium text-destructive">
                                                    Erreur
                                                </span>
                                                <p
                                                    class="text-xs text-destructive/90"
                                                    :title="row.integration_error"
                                                >
                                                    {{ row.integration_error }}
                                                </p>
                                            </div>
                                            <span
                                                v-else
                                                class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-900"
                                            >
                                                À intégrer
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            <div class="flex justify-end gap-1">
                                                <Button
                                                    v-if="!row.integrated && !row.integration_error"
                                                    size="sm"
                                                    variant="secondary"
                                                    @click="integrateRow(row.id)"
                                                >
                                                    Intégrer
                                                </Button>
                                                <Button
                                                    v-if="row.integration_error"
                                                    size="sm"
                                                    variant="outline"
                                                    @click="retryIntegrateRow(row.id)"
                                                >
                                                    Réessayer
                                                </Button>
                                                <Button
                                                    v-if="row.commande_id"
                                                    size="sm"
                                                    variant="ghost"
                                                    type="button"
                                                    @click="openDetail(row.commande_id!)"
                                                >
                                                    Voir
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <ModulePagination
                            :wrapper-class="modulePaginationWrapperClass"
                            :links="imports.links"
                            :from="imports.from"
                            :to="imports.to"
                            :total="imports.total"
                        />
                    </div>
                    <ModuleEmptyState
                        v-else
                        message="Aucune ligne importée pour l’instant."
                        hint="Chargez un fichier Excel ci-dessus, puis intégrez les lignes une par une ou en lot."
                    />
                </template>
            </div>
        </div>

        <template v-if="heavyUiMounted">
            <CommandeDetailDrawer
                ref="detailDrawerRef"
                :can-manage-commandes="canManageCommandesRef"
                :can-create-commande="canCreateCommande"
                :livreurs="modals.livreurs"
                :montants-livraison="modals.montantsLivraison"
                :modes-paiement="modals.modesPaiement"
                :parapharma-produit-types="modals.parapharmaProduitTypes"
                :motif-options="motifOptions"
                :motifs-relance="motifsRelance"
                :motif-label-by-slug="motifLabelBySlug"
                :ensure-referentiels="modals.ensureReferentiels"
                @open-recu="modals.onOpenRecu"
                @open-relancer="modals.onOpenRelancer"
            />

            <CommandeBulkAnnulerModal
                v-model:open="modals.showBulkAnnulerModal"
                v-model:motif="modals.motifBulkAnnulation"
                :selected-count="selectedCommandeIds.size"
                :motif-options="motifOptions"
                @confirm="modals.confirmBulkAnnuler()"
            />

            <CommandeEnregistrementModal
                v-model:open="modals.showEnregistrementModal"
                historical-entry
                allow-create-pharmacie
                :zones="modals.zones"
                :pharmacies="modals.pharmacies"
                :arrondissements="modals.arrondissements"
                :parapharma-produit-types="modals.parapharmaProduitTypes"
                :montants-livraison="modals.montantsLivraison"
                :api-errors="modals.apiErrorsEnreg"
                @pharmacie-created="modals.addPharmacie"
                @submit="modals.submitEnregistrementFromModal"
            />

            <RecuCommandeModal
                v-model:open="modals.showRecuModal"
                :commande="modals.recuCommande"
            />
        </template>

        <Dialog
            :open="importPurgeModalOpen"
            @update:open="(v: boolean) => (!v ? closeImportPurgeModal() : null)"
        >
            <DialogContent class="flex max-w-md flex-col gap-0 overflow-hidden p-0">
                <DialogHeader class="border-b px-6 py-4 text-left">
                    <DialogTitle class="text-lg text-destructive">
                        Vider les imports en attente
                    </DialogTitle>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Supprime uniquement les lignes Excel importées qui ne
                        sont pas encore intégrées (table de staging).
                    </p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-muted-foreground">
                        <li>Les commandes live dans « Commandes » ne sont pas touchées.</li>
                        <li>Les comptes utilisateurs ne sont pas touchés.</li>
                        <li>Les lignes déjà intégrées restent consultables.</li>
                    </ul>
                </DialogHeader>
                <div class="space-y-3 px-6 py-4">
                    <div>
                        <Label class="text-xs font-medium">
                            Tapez
                            <code class="rounded bg-muted px-1 py-0.5 text-xs">{{
                                importPurgePhrase
                            }}</code>
                            pour confirmer
                        </Label>
                        <Input
                            v-model="importPurgeConfirmInput"
                            class="mt-1.5"
                            autocomplete="off"
                            autocapitalize="characters"
                            :placeholder="importPurgePhrase"
                        />
                    </div>
                </div>
                <DialogFooter
                    class="gap-2 border-t bg-muted/30 px-6 py-4 sm:justify-between"
                >
                    <Button type="button" variant="outline" @click="closeImportPurgeModal">
                        Annuler
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="!canSubmitImportPurge"
                        @click="submitImportPurgeAll"
                    >
                        Vider les imports en attente
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <FlashToastHost />
    </AppLayout>
</template>
