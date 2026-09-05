<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import {
    computed,
    defineAsyncComponent,
    nextTick,
    ref,
    watch,
} from 'vue';
import CommandesTable from '@/components/commandes/CommandesTable.vue';
import CommandeStatusFilters from '@/components/commandes/CommandeStatusFilters.vue';
import ModuleEmptyState from '@/components/shared/ModuleEmptyState.vue';
import ModuleFilterPanel from '@/components/shared/ModuleFilterPanel.vue';
import ModuleInlineTabs from '@/components/shared/ModuleInlineTabs.vue';
import FlashToastHost from '@/components/FlashToastHost.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { useCommandeModals } from '@/composables/useCommandeModals';
import {
    getClientDisplayName,
} from '@/lib/commandeDetailDisplay';
import {
    moduleCardClass,
    moduleInputDateClass,
    modulePageClass,
    modulePrimaryButtonClass,
    moduleSelectClass,
} from '@/lib/bengadokUi';
import { dashboard } from '@/routes';
import { STATUTS_COMMANDE, commandeStatutLabel } from '@/types';
import type { BreadcrumbItem, MotifAnnulationOption } from '@/types';

/** Chunks lourds (tiroir ~1,5k lignes, modales + Uppy) — chargés à la demande. */
const CommandeDetailDrawer = defineAsyncComponent(
    () => import('@/components/commandes/CommandeDetailDrawer.vue'),
);
const CommandeEnregistrementModal = defineAsyncComponent(
    () => import('@/components/commandes/CommandeEnregistrementModal.vue'),
);
const RecuCommandeModal = defineAsyncComponent(
    () => import('@/components/commandes/RecuCommandeModal.vue'),
);
const CommandeBulkAnnulerModal = defineAsyncComponent(
    () => import('@/components/commandes/CommandeBulkAnnulerModal.vue'),
);

type CommandeDetailDrawerExpose = {
    openDetail: (id: number) => void;
    closeDetail: () => void;
};

const props = withDefaults(
    defineProps<{
        commandes?: {
            data: Array<{
                id: number;
                numero: string;
                date: string;
                status: string;
                prix_total: number;
                client: {
                    nom: string;
                    prenom: string;
                    tel: string;
                    adresse?: string;
                    sexe?: string;
                };
                pharmacie?: { designation: string };
                medicaments_resume: string;
                produits?: Array<{
                    designation: string;
                    dosage?: string;
                    pivot: { quantite: number };
                }>;
                montant_livraison?: { designation: number };
                mode_paiement?: { designation: string };
            }>;
            links: Array<{
                url: string | null;
                label: string;
                active: boolean;
            }>;
            total?: number;
        };
        stats?: Record<string, number>;
        filters?: {
            search?: string;
            status?: string;
            periode?: string;
            date?: string;
        };
        openDetailCommandeId?: number | null;
        canManageCommandes?: boolean;
    }>(),
    {
        commandes: () => ({ data: [], links: [] }),
        stats: () => ({}),
        filters: () => ({}),
        openDetailCommandeId: null,
        canManageCommandes: false,
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Commandes', href: '/commandes' },
];

const page = usePage();
const canCreateCommande = computed(() => {
    const roles =
        (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? [];
    return roles.some((r) =>
        ['admin', 'super_admin', 'agent_call_center'].includes(r),
    );
});

const canManageCommandesRef = computed(() => props.canManageCommandes);

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

const searchQuery = ref(props.filters.search ?? '');
const activeTab = ref<'gestion' | 'statistiques'>('gestion');
const commandeTabs = [
    { id: 'gestion', label: 'Gestion commandes' },
    { id: 'statistiques', label: 'Statistiques' },
] as const;
const detailDrawerRef = ref<CommandeDetailDrawerExpose | null>(null);

/** Monte tiroir + modales uniquement quand nécessaire (réduit le JS initial). */
const heavyUiMounted = ref(!!props.openDetailCommandeId);

function mountHeavyUi(): void {
    heavyUiMounted.value = true;
}

const selectedIds = ref<Set<number>>(new Set());

function clearSelection() {
    selectedIds.value = new Set();
}

const modals = useCommandeModals({
    canManageCommandes: canManageCommandesRef,
    selectedIds,
    clearSelection,
    onRelanceSuccess: () => detailDrawerRef.value?.closeDetail(),
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
        modals.showEnregistrementModal.value,
        modals.showRelancerModal.value,
        modals.showRecuModal.value,
        modals.showBulkAnnulerModal.value,
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

const allSelected = computed(() => {
    const data = props.commandes?.data ?? [];
    return data.length > 0 && data.every((c) => selectedIds.value.has(c.id));
});
const someSelected = computed(() => selectedIds.value.size > 0);

function toggleAll() {
    const data = props.commandes?.data ?? [];
    if (allSelected.value) {
        data.forEach((c) => selectedIds.value.delete(c.id));
    } else {
        data.forEach((c) => selectedIds.value.add(c.id));
    }
    selectedIds.value = new Set(selectedIds.value);
}
function toggleOne(id: number) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
}

function exportSelectedCSV() {
    const data = props.commandes?.data ?? [];
    const selected = data.filter((c) => selectedIds.value.has(c.id));
    if (!selected.length) return;
    const headers = [
        'N°',
        'Client',
        'Tél',
        'Date',
        'Adresse',
        'Médicaments',
        'Montant',
        'Statut',
    ];
    const rows = selected.map((c) => [
        c.numero,
        getClientDisplayName(c.client),
        c.client?.tel ?? '',
        c.date ?? '',
        c.client?.adresse ?? '-',
        c.medicaments_resume ?? '-',
        Number(c.prix_total).toLocaleString('fr-FR'),
        commandeStatutLabel(c.status),
    ]);
    const csv = [
        headers.join(';'),
        ...rows.map((r) =>
            r.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(';'),
        ),
    ].join('\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `commandes_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

watch(
    () => props.filters.search,
    (v) => {
        searchQuery.value = v ?? '';
    },
);

const statuts = STATUTS_COMMANDE;

type CommandeFilters = {
    search?: string;
    status?: string;
    periode?: string;
    date?: string;
};

function filtrer(key: string, value: string) {
    const raw: CommandeFilters = { ...props.filters };
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

    const params: Record<string, string> = {};
    if (raw.search) params.search = raw.search;
    if (raw.status) params.status = raw.status;
    if (raw.periode) params.periode = raw.periode;
    if (raw.date) params.date = raw.date;

    router.get('/commandes', params, {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Gestion des commandes - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="modulePageClass">
            <ModuleInlineTabs
                v-model="activeTab"
                :tabs="[...commandeTabs]"
            />

            <div v-if="activeTab === 'gestion'" class="space-y-6">
                <ModuleFilterPanel
                    v-model:search="searchQuery"
                    placeholder="Recherche commandes (médicaments, téléphone, noms…)"
                    :counter="commandes?.total ?? 0"
                    @submit="filtrer('search', searchQuery)"
                >
                    <span class="text-sm font-medium text-muted-foreground"
                        >Période</span
                    >
                    <select
                        :value="filters.periode ?? ''"
                        :class="moduleSelectClass"
                        @change="
                            (e: Event) =>
                                filtrer(
                                    'periode',
                                    (e.target as HTMLSelectElement).value,
                                )
                        "
                    >
                        <option value="">Toutes les dates</option>
                        <option value="aujourdhui">Aujourd'hui</option>
                        <option value="semaine">Cette semaine</option>
                        <option value="mois">Ce mois</option>
                    </select>
                    <input
                        :value="filters.date ?? ''"
                        type="date"
                        title="Une date précise remplace le filtre période"
                        :class="moduleInputDateClass"
                        @input="
                            (e: Event) =>
                                filtrer(
                                    'date',
                                    (e.target as HTMLInputElement).value,
                                )
                        "
                    />
                    <template #actions>
                        <Button
                            v-if="canCreateCommande"
                            type="button"
                            :class="[modulePrimaryButtonClass, 'gap-2']"
                            @mouseenter="prefetchCommandeHeavyUi"
                            @focus="prefetchCommandeHeavyUi"
                            @click="modals.openEnregistrementModal()"
                        >
                            <Plus class="size-4" />
                            Nouvelle commande
                        </Button>
                    </template>
                </ModuleFilterPanel>

                <CommandeStatusFilters
                    :statuts="statuts"
                    :stats="stats ?? {}"
                    :active-status="filters.status"
                    @filter="(status) => filtrer('status', status)"
                />

                <div :class="[moduleCardClass, 'overflow-hidden']">
                    <CommandesTable
                        :commandes="commandes"
                        :stats="stats ?? {}"
                        :filters="filters ?? {}"
                        :statuts="statuts"
                        :selected-ids="selectedIds"
                        :all-selected="allSelected"
                        :some-selected="someSelected"
                        :can-create-commande="canCreateCommande"
                        @toggle-all="toggleAll"
                        @toggle-one="toggleOne"
                        @clear-selection="clearSelection"
                        @export-csv="exportSelectedCSV"
                        @open-bulk-annuler-modal="modals.openBulkAnnulerModal()"
                        @open-detail="openDetail"
                    />
                </div>
            </div>

            <ModuleEmptyState
                v-else
                message="Statistiques – à venir"
            />
        </div>

        <template v-if="heavyUiMounted">
            <CommandeDetailDrawer
                ref="detailDrawerRef"
                :can-manage-commandes="canManageCommandes"
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
                :selected-count="selectedIds.size"
                :motif-options="motifOptions"
                @confirm="modals.confirmBulkAnnuler()"
            />

            <CommandeEnregistrementModal
                v-model:open="modals.showEnregistrementModal"
                :zones="modals.zones ?? []"
                :pharmacies="modals.pharmacies"
                :arrondissements="modals.arrondissements"
                :parapharma-produit-types="modals.parapharmaProduitTypes"
                :montants-livraison="modals.montantsLivraison"
                :api-errors="modals.apiErrorsEnreg"
                @submit="modals.submitEnregistrementFromModal"
            />

            <CommandeEnregistrementModal
                v-model:open="modals.showRelancerModal"
                mode="relance"
                :commande="modals.relancerCommande ?? undefined"
                :zones="modals.zones ?? []"
                :pharmacies="modals.pharmacies"
                :arrondissements="modals.arrondissements"
                :parapharma-produit-types="modals.parapharmaProduitTypes"
                :montants-livraison="modals.montantsLivraison"
                :api-errors="modals.errorsRelancer"
                @submit="modals.submitRelancerFromModal"
            />

            <RecuCommandeModal
                v-model:open="modals.showRecuModal"
                :commande="modals.recuCommande"
            />
        </template>

        <FlashToastHost />
    </AppLayout>
</template>
