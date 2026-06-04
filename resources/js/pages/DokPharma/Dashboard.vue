<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Banknote,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Coins,
    Package,
    Percent,
    Pill,
    Sparkles,
    TrendingUp,
} from 'lucide-vue-next';
import { computed } from 'vue';
import PharmacyKpiCard from '@/components/pharmacy/PharmacyKpiCard.vue';
import PharmacyModernChart from '@/components/pharmacy/PharmacyModernChart.vue';
import PharmacyLayout from '@/layouts/PharmacyLayout.vue';

type PointChart = { label: string; valeur: number };
type MeilleurVente = {
    id: number;
    designation: string;
    ca: number;
    quantite: number;
};
type MedicamentIndispo = {
    id: number;
    designation: string;
    dosage: string | null;
    quantite_demandee: number;
    nb_commandes: number;
};

const inertiaOnlyKeys = [
    'stats',
    'config',
    'commission_periode',
    'volumeParJour',
    'creditsEvolutionParJour',
    'meilleursVentes',
    'medicamentsIndisponibles',
    'period',
    'chart_offset',
] as const;

const props = withDefaults(
    defineProps<{
        stats: {
            revenu_total?: number;
            ca_medicaments?: number;
            ca_parapharma?: number;
            nb_commandes_traitees?: number;
            montant_commissions?: number;
            ca_parapharma_periode_commission?: number;
            credits_disponibles?: number;
        };
        config?: { commission_percent: number };
        commission_periode?: { label: string };
        volumeParJour: PointChart[];
        creditsEvolutionParJour: PointChart[];
        meilleursVentes: MeilleurVente[];
        medicamentsIndisponibles: MedicamentIndispo[];
        period?: 'week' | 'month';
        chart_offset?: number;
    }>(),
    {
        period: 'month',
        chart_offset: 0,
        config: () => ({ commission_percent: 1 }),
        commission_periode: () => ({ label: '' }),
        volumeParJour: () => [],
        creditsEvolutionParJour: () => [],
        meilleursVentes: () => [],
        medicamentsIndisponibles: () => [],
    },
);

const commissionPercent = computed(
    () => props.config?.commission_percent ?? 1,
);

const periodLabel = computed(() => {
    if (props.chart_offset === 0) {
        return props.period === 'week' ? 'Cette semaine' : 'Ce mois';
    }
    return props.period === 'week'
        ? 'Semaine sélectionnée'
        : 'Mois sélectionné';
});

const maxVenteCa = computed(() =>
    Math.max(1, ...props.meilleursVentes.map((v) => v.ca)),
);

function setPeriod(period: 'week' | 'month') {
    if (period === props.period && props.chart_offset === 0) {
        return;
    }
    router.get(
        '/dok-pharma',
        { period, chart_offset: 0 },
        { preserveScroll: true, only: [...inertiaOnlyKeys] },
    );
}

function navigateChart(delta: number): void {
    const next = props.chart_offset + delta;
    if (next > 0) {
        return;
    }
    router.get(
        '/dok-pharma',
        { period: props.period, chart_offset: next },
        { preserveScroll: true, only: [...inertiaOnlyKeys] },
    );
}

function formatXaf(value: number | undefined): string {
    return `${(value ?? 0).toLocaleString('fr-FR')} XAF`;
}

const kpiCommandes = computed(
    () => `${props.stats.nb_commandes_traitees ?? 0} commandes`,
);
const kpiCa = computed(() => formatXaf(props.stats.revenu_total));
const kpiCaHint = computed(
    () =>
        `Méd. ${(props.stats.ca_medicaments ?? 0).toLocaleString('fr-FR')} · Para. ${(props.stats.ca_parapharma ?? 0).toLocaleString('fr-FR')} XAF`,
);
const kpiCommission = computed(() => formatXaf(props.stats.montant_commissions));
const kpiCommissionHint = computed(
    () =>
        `Période ${props.commission_periode?.label || '—'} · ${commissionPercent.value}% sur ${(props.stats.ca_parapharma_periode_commission ?? 0).toLocaleString('fr-FR')} XAF`,
);
</script>

<template>
    <Head title="Tableau de bord - BengaDok" />

    <PharmacyLayout>
        <div class="pharmacy-content-shell space-y-5 pb-10 sm:space-y-6">
            <!-- En-tête -->
            <header
                class="pharmacy-dash-panel flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#3995d2] to-[#5bb66e] text-white shadow-lg shadow-sky-500/25"
                    >
                        <Sparkles class="size-6" stroke-width="2" />
                    </div>
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-widest text-slate-500"
                        >
                            Espace pharmacie
                        </p>
                        <h1
                            class="mt-0.5 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl"
                        >
                            Tableau de bord
                        </h1>
                        <p
                            class="mt-1 flex items-center gap-1.5 text-sm text-slate-500"
                        >
                            <CalendarDays class="size-4 shrink-0" />
                            {{ periodLabel }}
                        </p>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3 sm:justify-end"
                >
                    <div class="pharmacy-dash-segment">
                        <button
                            type="button"
                            :class="{ 'is-active': period === 'month' }"
                            @click="setPeriod('month')"
                        >
                            Ce mois
                        </button>
                        <button
                            type="button"
                            :class="{ 'is-active': period === 'week' }"
                            @click="setPeriod('week')"
                        >
                            Cette semaine
                        </button>
                    </div>
                    <div
                        class="flex items-center gap-1 rounded-full bg-slate-100/80 p-1"
                    >
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-full text-slate-600 transition hover:bg-white hover:shadow-sm disabled:opacity-30"
                            :disabled="chart_offset <= -52"
                            aria-label="Période précédente"
                            @click="navigateChart(-1)"
                        >
                            <ChevronLeft class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-full text-slate-600 transition hover:bg-white hover:shadow-sm disabled:opacity-30"
                            :disabled="chart_offset >= 0"
                            aria-label="Période suivante"
                            @click="navigateChart(1)"
                        >
                            <ChevronRight class="size-4" />
                        </button>
                    </div>
                </div>
            </header>

            <!-- KPI -->
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <PharmacyKpiCard
                    label="Commandes traitées"
                    :value="kpiCommandes"
                    :icon="Package"
                    accent="green"
                />
                <PharmacyKpiCard
                    label="CA commandes"
                    :value="kpiCa"
                    :hint="kpiCaHint"
                    :icon="Banknote"
                    accent="blue"
                />
                <PharmacyKpiCard
                    label="Commissions à reverser"
                    :value="kpiCommission"
                    :hint="kpiCommissionHint"
                    :icon="Percent"
                    accent="amber"
                />
            </section>

            <!-- Graphiques -->
            <section class="grid gap-5 lg:grid-cols-2">
                <div class="pharmacy-dash-card p-5 sm:p-6">
                    <div class="mb-6 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600"
                            >
                                <TrendingUp class="size-4" />
                            </span>
                            <div>
                                <h2 class="font-semibold text-slate-900">
                                    Volume traité
                                </h2>
                                <p class="text-xs text-slate-500">
                                    Commandes par jour
                                </p>
                            </div>
                        </div>
                    </div>
                    <PharmacyModernChart
                        :points="volumeParJour"
                        variant="green"
                        unit="cmd"
                    />
                </div>

                <div class="pharmacy-dash-card p-5 sm:p-6">
                    <div class="mb-6 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600"
                            >
                                <Coins class="size-4" />
                            </span>
                            <div>
                                <h2 class="font-semibold text-slate-900">
                                    Évolution des crédits
                                </h2>
                                <p class="text-xs text-slate-500">
                                    Solde :
                                    <span class="font-semibold text-slate-800"
                                        >{{
                                            stats.credits_disponibles ?? 0
                                        }}
                                        crédits</span
                                    >
                                </p>
                            </div>
                        </div>
                    </div>
                    <PharmacyModernChart
                        :points="creditsEvolutionParJour"
                        variant="blue"
                        unit="crédits"
                    />
                </div>
            </section>

            <!-- Listes détaillées -->
            <section class="grid gap-5 xl:grid-cols-2">
                <!-- Top ventes -->
                <div class="pharmacy-dash-card flex flex-col p-5 sm:p-6">
                    <div class="mb-5 flex items-center gap-3">
                        <span
                            class="flex size-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600"
                        >
                            <Pill class="size-4" />
                        </span>
                        <div>
                            <h2 class="font-semibold text-slate-900">
                                Médicaments les plus vendus
                            </h2>
                            <p class="text-xs text-slate-500">
                                Détail des ventes sur la période
                            </p>
                        </div>
                    </div>

                    <ul
                        v-if="meilleursVentes.length"
                        class="max-h-[400px] space-y-3 overflow-y-auto pr-1"
                    >
                        <li
                            v-for="(item, index) in meilleursVentes"
                            :key="item.id"
                            class="rounded-xl border border-slate-100 bg-slate-50/80 p-3 transition hover:border-slate-200 hover:bg-white"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold tabular-nums"
                                    :class="
                                        index === 0
                                            ? 'bg-emerald-500 text-white'
                                            : index === 1
                                              ? 'bg-sky-500/90 text-white'
                                              : index === 2
                                                ? 'bg-slate-400 text-white'
                                                : 'bg-slate-200 text-slate-600'
                                    "
                                >
                                    {{ index + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate font-medium text-slate-900"
                                    >
                                        {{ item.designation }}
                                    </p>
                                    <div
                                        class="mt-2 flex items-center justify-between gap-2 text-xs"
                                    >
                                        <span class="text-slate-500"
                                            >Qté
                                            {{
                                                Number(
                                                    item.quantite,
                                                ).toLocaleString('fr-FR', {
                                                    maximumFractionDigits: 2,
                                                })
                                            }}</span
                                        >
                                        <span
                                            class="font-semibold tabular-nums text-slate-900"
                                            >{{
                                                Math.round(
                                                    item.ca,
                                                ).toLocaleString('fr-FR')
                                            }}
                                            XAF</span
                                        >
                                    </div>
                                    <div
                                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200/80"
                                    >
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all duration-500"
                                            :style="{
                                                width: `${Math.max(4, (item.ca / maxVenteCa) * 100)}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div
                        v-else
                        class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 py-12 text-center"
                    >
                        <Package
                            class="mb-2 size-8 text-slate-300"
                            stroke-width="1.5"
                        />
                        <p class="text-sm text-slate-500">
                            Aucune vente sur cette période
                        </p>
                    </div>
                </div>

                <!-- Indisponibles -->
                <div class="pharmacy-dash-card flex flex-col p-5 sm:p-6">
                    <div class="mb-5 flex items-center gap-3">
                        <span
                            class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600"
                        >
                            <AlertTriangle class="size-4" />
                        </span>
                        <div>
                            <h2 class="font-semibold text-slate-900">
                                Médicaments indisponibles
                            </h2>
                            <p class="text-xs text-slate-500">
                                Demandes non satisfaites
                            </p>
                        </div>
                        <span
                            v-if="medicamentsIndisponibles.length"
                            class="ml-auto rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800"
                        >
                            {{ medicamentsIndisponibles.length }}
                        </span>
                    </div>

                    <ul
                        v-if="medicamentsIndisponibles.length"
                        class="max-h-[400px] space-y-2 overflow-y-auto pr-1"
                    >
                        <li
                            v-for="row in medicamentsIndisponibles"
                            :key="`${row.id}-${row.dosage ?? ''}`"
                            class="flex items-center gap-3 rounded-xl border-l-4 border-l-amber-400 bg-amber-50/60 px-3 py-3 transition hover:bg-amber-50"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-slate-900">
                                    {{ row.designation }}
                                </p>
                                <p
                                    v-if="row.dosage"
                                    class="text-xs text-slate-500"
                                >
                                    {{ row.dosage }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right text-xs">
                                <p
                                    class="font-semibold tabular-nums text-slate-800"
                                >
                                    {{ row.quantite_demandee }} unités
                                </p>
                                <p class="text-slate-500">
                                    {{ row.nb_commandes }} cmd.
                                </p>
                            </div>
                        </li>
                    </ul>
                    <div
                        v-else
                        class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 py-12 text-center"
                    >
                        <AlertTriangle
                            class="mb-2 size-8 text-slate-300"
                            stroke-width="1.5"
                        />
                        <p class="text-sm text-slate-500">
                            Aucune indisponibilité sur cette période
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </PharmacyLayout>
</template>
