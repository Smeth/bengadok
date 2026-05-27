<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    MoreVertical,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';
import { computed } from 'vue';
import PharmacyLayout from '@/layouts/PharmacyLayout.vue';

type StatCard = {
    revenu_total?: number;
    ca_medicaments?: number;
    ca_parapharma?: number;
    pct_revenu?: number;
    nb_commandes?: number;
    pct_commandes?: number;
    nb_commandes_traitees?: number;
    pct_commandes_traitees?: number;
    montant_commissions?: number;
    ca_parapharma_periode_commission?: number;
    nb_clients?: number;
    pct_clients?: number;
    credits_disponibles?: number;
    credits_utilises_periode?: number;
    cout_credits_periode?: number;
};
type PointChart = {
    label: string;
    valeur: number;
    medicaments?: number;
    parapharma?: number;
};
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
    'revenusParJour',
    'volumeParJour',
    'creditsEvolutionParJour',
    'meilleursVentes',
    'medicamentsIndisponibles',
    'period',
    'chart_offset',
] as const;

const props = withDefaults(
    defineProps<{
        stats: StatCard;
        config?: {
            commission_percent: number;
            periode_jour_fin: number;
            commission_jour_echeance: number;
            credit_prix_unitaire_xaf: number;
            credit_seuil_medicament_xaf: number;
        };
        commission_periode?: {
            debut: string;
            fin: string;
            label: string;
        };
        revenusParJour: PointChart[];
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
        config: () => ({
            commission_percent: 1,
            periode_jour_fin: 25,
            commission_jour_echeance: 25,
            credit_prix_unitaire_xaf: 150,
            credit_seuil_medicament_xaf: 5000,
        }),
        commission_periode: () => ({
            debut: '',
            fin: '',
            label: '',
        }),
        creditsEvolutionParJour: () => [],
        medicamentsIndisponibles: () => [],
    },
);

const commissionPercent = computed(
    () => props.config?.commission_percent ?? 1,
);

const comparisonLabel = computed(() =>
    props.period === 'week'
        ? 'vs semaine précédente'
        : 'vs période comparable (mois préc.)',
);

const periodSelectValue = computed({
    get: () => props.period,
    set: (v: string) => {
        if (v !== 'week' && v !== 'month') {
            return;
        }
        router.get(
            '/dok-pharma',
            { period: v, chart_offset: 0 },
            {
                preserveScroll: true,
                only: [...inertiaOnlyKeys],
            },
        );
    },
});

function navigateChart(delta: number): void {
    const next = props.chart_offset + delta;
    if (next > 0) {
        return;
    }
    router.get(
        '/dok-pharma',
        { period: props.period, chart_offset: next },
        {
            preserveScroll: true,
            only: [...inertiaOnlyKeys],
        },
    );
}

const maxRevenuRaw = Math.max(1, ...props.revenusParJour.map((p) => p.valeur));
/** Plafond d’échelle « nice » pour barres + axe Y cohérents */
const revenuScaleMax = computed(() =>
    Math.max(5000, Math.ceil(maxRevenuRaw / 5000) * 5000),
);
const maxVolume = Math.max(1, ...props.volumeParJour.map((p) => p.valeur));
const maxCa = Math.max(1, ...props.meilleursVentes.map((m) => m.ca));
const maxCreditsEvolution = Math.max(
    1,
    ...props.creditsEvolutionParJour.map((p) => p.valeur),
);

const barColors = ['#5bb66e', '#3995d2', '#5c5959', '#6abb7b', '#4a9fd4'];

/** Axe Y type maquette (5K, 10K, …) aligné sur revenuScaleMax */
const revenuYTicks = computed(() => {
    const niceMax = revenuScaleMax.value;
    const step = niceMax <= 25000 ? 5000 : Math.ceil(niceMax / 5 / 5000) * 5000;
    const ticks: number[] = [];
    for (let v = 0; v <= niceMax; v += step) {
        ticks.push(v);
    }
    if (ticks[ticks.length - 1]! < niceMax) {
        ticks.push(niceMax);
    }
    return ticks.slice(-6).reverse();
});

function formatAxisK(n: number): string {
    if (n >= 1000) {
        return `${Math.round(n / 1000)}K`;
    }
    return String(n);
}
</script>

<template>
    <Head title="Tableau de bord - BengaDok" />

    <PharmacyLayout>
        <div class="pharmacy-content-shell">
            <!-- Bannière Figma 2855:728 — 1346×257, placements 2855:730 / 2855:731 -->
            <div
                class="relative z-[1] mx-auto mb-6 w-full max-w-[1346px] pb-28 lg:pb-36"
            >
                <div
                    class="pharmacy-card pharmacy-card--hero pharmacy-hero-figma"
                >
                    <p
                        class="pharmacy-hero-figma__title px-6 pb-3 pt-6 lg:pb-0 lg:pt-0 lg:px-0"
                    >
                        Créez de la proximité avec votre clientèle
                    </p>
                    <!-- Mobile / tablette : aperçu compact -->
                    <div
                        class="pointer-events-none flex items-end justify-center gap-2 px-4 pb-6 pt-1 sm:gap-4 lg:hidden"
                        aria-hidden="true"
                    >
                        <img
                            src="/images/pharmacy/dashboard-hero-phone-hand.png"
                            alt=""
                            class="h-[120px] w-auto max-w-[42%] object-contain object-bottom sm:h-[140px]"
                            width="317"
                            height="280"
                        />
                        <img
                            src="/images/pharmacy/dashboard-hero-scene.png"
                            alt=""
                            class="h-[128px] w-auto max-w-[48%] object-contain object-bottom sm:h-[152px] sm:max-w-[52%] rotate-[3.46deg]"
                            width="550"
                            height="550"
                        />
                    </div>
                    <!-- Desktop : positions absolues = maquette Figma -->
                    <div
                        class="pharmacy-hero-figma__phone hidden lg:block"
                        aria-hidden="true"
                    >
                        <img
                            src="/images/pharmacy/dashboard-hero-phone-hand.png"
                            alt=""
                            width="317"
                            height="317"
                        />
                    </div>
                    <div
                        class="pharmacy-hero-figma__scene hidden lg:flex lg:items-center lg:justify-center"
                        aria-hidden="true"
                    >
                        <img
                            src="/images/pharmacy/dashboard-hero-scene.png"
                            alt=""
                            width="551"
                            height="551"
                        />
                    </div>
                </div>
            </div>

            <!-- KPI — CA, traitées, commissions, clients -->
            <div class="relative z-[2] mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="pharmacy-card p-5 sm:p-6">
                    <div class="mb-2 flex items-start justify-between">
                        <p class="text-sm font-black text-black sm:text-base">
                            CA commandes
                        </p>
                        <button
                            type="button"
                            class="rounded-lg p-1 text-black/40 hover:bg-black/5"
                            aria-label="Options"
                        >
                            <MoreVertical class="size-5" />
                        </button>
                    </div>
                    <p
                        class="text-[clamp(1.75rem,4vw,2.8rem)] font-black tabular-nums leading-none text-black"
                    >
                        {{ stats.revenu_total?.toLocaleString('fr-FR') ?? 0 }}
                        <span
                            class="text-lg font-black text-black/80 sm:text-xl"
                            >XAF</span
                        >
                    </p>
                    <p class="mt-2 text-xs font-medium leading-snug text-black/55">
                        Médicaments :
                        {{
                            (stats.ca_medicaments ?? 0).toLocaleString('fr-FR')
                        }}
                        XAF · Parapharmacie :
                        {{
                            (stats.ca_parapharma ?? 0).toLocaleString('fr-FR')
                        }}
                        XAF
                    </p>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <span
                            class="inline-flex items-center gap-1 rounded-[13px] border border-black px-2.5 py-1 text-xs font-black text-black"
                        >
                            <TrendingUp
                                v-if="(stats.pct_revenu ?? 0) >= 0"
                                class="size-3.5 shrink-0"
                            />
                            <TrendingDown v-else class="size-3.5 shrink-0" />
                            {{ (stats.pct_revenu ?? 0) >= 0 ? '+' : ''
                            }}{{ stats.pct_revenu ?? 0 }}%
                        </span>
                        <span class="text-[15px] font-black text-black">{{
                            comparisonLabel
                        }}</span>
                    </div>
                </div>
                <div class="pharmacy-card p-5 sm:p-6">
                    <div class="mb-2 flex items-start justify-between">
                        <p class="text-sm font-black text-black sm:text-base">
                            Commandes traitées
                        </p>
                        <button
                            type="button"
                            class="rounded-lg p-1 text-black/40 hover:bg-black/5"
                            aria-label="Options"
                        >
                            <MoreVertical class="size-5" />
                        </button>
                    </div>
                    <p
                        class="text-[clamp(1.75rem,4vw,2.8rem)] font-black tabular-nums leading-none text-black"
                    >
                        {{ stats.nb_commandes_traitees ?? 0 }}
                        <span
                            class="text-lg font-black text-black/80 sm:text-xl"
                            >cmd</span
                        >
                    </p>
                    <p
                        class="mt-1 text-xs font-medium text-black/55"
                        title="Inclut prise en charge, indisponible, à préparer, retirées"
                    >
                        Prises en charge (statut après « nouvelle »)
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <span
                            class="inline-flex items-center gap-1 rounded-[13px] border border-black px-2.5 py-1 text-xs font-black text-black"
                        >
                            <TrendingUp
                                v-if="(stats.pct_commandes_traitees ?? 0) >= 0"
                                class="size-3.5 shrink-0"
                            />
                            <TrendingDown v-else class="size-3.5 shrink-0" />
                            {{
                                (stats.pct_commandes_traitees ?? 0) >= 0
                                    ? '+'
                                    : ''
                            }}{{ stats.pct_commandes_traitees ?? 0 }}%
                        </span>
                        <span class="text-[15px] font-black text-black">{{
                            comparisonLabel
                        }}</span>
                    </div>
                    <p class="mt-3 border-t border-black/10 pt-2 text-xs text-black/60">
                        Flux période (dont nouvelles) :
                        <span class="font-bold text-black">{{
                            stats.nb_commandes ?? 0
                        }}</span>
                        commandes
                        <span
                            class="ml-2 inline-flex items-center gap-0.5 text-[11px]"
                        >
                            <TrendingUp
                                v-if="(stats.pct_commandes ?? 0) >= 0"
                                class="size-3 shrink-0"
                            />
                            <TrendingDown v-else class="size-3 shrink-0" />
                            {{ (stats.pct_commandes ?? 0) >= 0 ? '+' : ''
                            }}{{ stats.pct_commandes ?? 0 }}%</span
                        >
                    </p>
                </div>
                <div class="pharmacy-card p-5 sm:p-6">
                    <div class="mb-2 flex items-start justify-between">
                        <p class="text-sm font-black text-black sm:text-base">
                            Commissions parapharmacie
                        </p>
                        <button
                            type="button"
                            class="rounded-lg p-1 text-black/40 hover:bg-black/5"
                            aria-label="Options"
                        >
                            <MoreVertical class="size-5" />
                        </button>
                    </div>
                    <p
                        class="text-[clamp(1.75rem,4vw,2.8rem)] font-black tabular-nums leading-none text-black"
                    >
                        {{
                            stats.montant_commissions?.toLocaleString(
                                'fr-FR',
                            ) ?? 0
                        }}
                        <span
                            class="text-lg font-black text-black/80 sm:text-xl"
                            >XAF</span
                        >
                    </p>
                    <p class="mt-2 text-xs font-medium leading-snug text-black/55">
                        Période commission
                        {{ commission_periode?.label ?? '—' }} ·
                        {{ commissionPercent }}&nbsp;% du CA parapharmacie
                        ({{
                            (
                                stats.ca_parapharma_periode_commission ?? 0
                            ).toLocaleString('fr-FR')
                        }}
                        XAF)
                    </p>
                </div>
                <div class="pharmacy-card p-5 sm:p-6">
                    <div class="mb-2 flex items-start justify-between">
                        <p class="text-sm font-black text-black sm:text-base">
                            Crédits BengaDok
                        </p>
                        <button
                            type="button"
                            class="rounded-lg p-1 text-black/40 hover:bg-black/5"
                            aria-label="Options"
                        >
                            <MoreVertical class="size-5" />
                        </button>
                    </div>
                    <p
                        class="text-[clamp(1.75rem,4vw,2.8rem)] font-black tabular-nums leading-none text-black"
                    >
                        {{ stats.credits_disponibles ?? 0 }}
                        <span
                            class="text-lg font-black text-black/80 sm:text-xl"
                            >crédits</span
                        >
                    </p>
                    <p class="mt-2 text-xs font-medium leading-snug text-black/55">
                        Utilisés sur la période
                        {{ commission_periode?.label ?? '—' }} :
                        <span class="font-bold text-black">{{
                            stats.credits_utilises_periode ?? 0
                        }}</span>
                        ({{
                            (
                                stats.cout_credits_periode ?? 0
                            ).toLocaleString('fr-FR')
                        }}
                        XAF)
                    </p>
                    <p class="mt-3 text-xs text-black/60">
                        Seuil éligibilité :
                        {{
                            (
                                config?.credit_seuil_medicament_xaf ?? 5000
                            ).toLocaleString('fr-FR')
                        }}
                        XAF médicaments ·
                        {{ config?.credit_prix_unitaire_xaf ?? 150 }} XAF /
                        crédit
                    </p>
                </div>
            </div>

            <!-- Analyse revenus — barres pilule (Figma) -->
            <div class="pharmacy-card mb-6 p-5 sm:p-6">
                <div
                    class="mb-6 flex flex-wrap items-center justify-between gap-3"
                >
                    <h2
                        class="text-xl font-black text-black sm:text-[30px] sm:leading-none"
                    >
                        Analyse des revenus
                    </h2>
                    <div class="flex items-center gap-2">
                        <select
                            v-model="periodSelectValue"
                            class="rounded-[13px] border border-black bg-white px-3 py-1.5 text-[15px] font-black text-black focus:outline-none focus:ring-2 focus:ring-[#3995d2]/30"
                        >
                            <option value="month">Ce mois</option>
                            <option value="week">Cette semaine</option>
                        </select>
                        <button
                            type="button"
                            class="flex size-[21px] items-center justify-center rounded-full border border-black bg-white disabled:opacity-40"
                            :disabled="chart_offset <= -52"
                            aria-label="Période précédente"
                            @click="navigateChart(-1)"
                        >
                            <ChevronLeft class="size-3" />
                        </button>
                        <button
                            type="button"
                            class="flex size-[21px] items-center justify-center rounded-full border border-black bg-white disabled:opacity-40"
                            :disabled="chart_offset >= 0"
                            aria-label="Période suivante"
                            @click="navigateChart(1)"
                        >
                            <ChevronRight class="size-3" />
                        </button>
                    </div>
                </div>
                <div class="flex gap-2 sm:gap-3">
                    <div
                        class="flex h-[220px] w-8 shrink-0 flex-col justify-between py-1 text-right text-xs font-medium text-black sm:w-9 sm:text-sm"
                    >
                        <span v-for="tick in revenuYTicks" :key="tick">{{
                            formatAxisK(tick)
                        }}</span>
                    </div>
                    <div
                        class="flex min-h-[220px] min-w-0 flex-1 items-end justify-between gap-1 overflow-x-auto pb-6 sm:gap-2"
                    >
                        <template v-for="(point, i) in revenusParJour" :key="i">
                            <div
                                class="flex min-w-[36px] flex-1 flex-col items-center gap-2 sm:min-w-[44px]"
                            >
                                <div
                                    class="flex h-[200px] w-full max-w-[72px] flex-col justify-end rounded-[50px] bg-[rgba(92,89,89,0.17)] p-1 sm:max-w-[88px]"
                                >
                                    <div
                                        class="w-full rounded-[50px] bg-[#5bb66e] transition-all"
                                        :style="{
                                            height: `${Math.max(2, (point.valeur / revenuScaleMax) * 100)}%`,
                                            minHeight:
                                                point.valeur > 0 ? '8px' : '0',
                                        }"
                                    />
                                </div>
                                <span class="text-xs font-medium text-black">{{
                                    point.label
                                }}</span>
                            </div>
                        </template>
                        <p
                            v-if="!revenusParJour.length"
                            class="w-full py-12 text-center text-sm text-black/60"
                        >
                            Aucune donnée sur cette période
                        </p>
                    </div>
                </div>
            </div>

            <!-- Volume + Meilleures ventes -->
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="pharmacy-card p-5 sm:p-6">
                    <div
                        class="mb-5 flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2 class="text-xl font-black text-black sm:text-2xl">
                            Volume de commandes traitées
                        </h2>
                        <select
                            v-model="periodSelectValue"
                            class="rounded-[13px] border border-black bg-white px-3 py-1.5 text-xs font-black focus:outline-none focus:ring-2 focus:ring-[#3995d2]/30"
                        >
                            <option value="week">Cette semaine</option>
                            <option value="month">Ce mois</option>
                        </select>
                    </div>
                    <div
                        class="flex min-h-[160px] items-end justify-between gap-1 overflow-x-auto pb-2 sm:gap-2"
                    >
                        <template v-for="(point, i) in volumeParJour" :key="i">
                            <div
                                class="flex min-w-[32px] flex-1 flex-col items-center gap-2 sm:min-w-[40px]"
                            >
                                <div
                                    class="flex h-[120px] w-full max-w-[64px] flex-col justify-end rounded-[50px] bg-[rgba(92,89,89,0.17)] p-1"
                                >
                                    <div
                                        class="w-full rounded-[50px] bg-[#6abb7b] transition-all"
                                        :style="{
                                            height: `${Math.max(2, (point.valeur / maxVolume) * 100)}%`,
                                            minHeight:
                                                point.valeur > 0 ? '8px' : '0',
                                        }"
                                    />
                                </div>
                                <span
                                    class="text-[11px] font-medium text-black sm:text-xs"
                                    >{{ point.label }}</span
                                >
                            </div>
                        </template>
                        <p
                            v-if="!volumeParJour.length"
                            class="w-full py-8 text-center text-sm text-black/60"
                        >
                            Aucune donnée sur cette période
                        </p>
                    </div>
                </div>

                <div class="pharmacy-card p-5 sm:p-6">
                    <div
                        class="mb-5 flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2 class="text-xl font-black text-black sm:text-2xl">
                            Meilleures ventes
                        </h2>
                        <select
                            v-model="periodSelectValue"
                            class="rounded-[13px] border border-black bg-white px-3 py-1.5 text-xs font-black focus:outline-none focus:ring-2 focus:ring-[#3995d2]/30"
                        >
                            <option value="month">Ce mois</option>
                            <option value="week">Cette semaine</option>
                        </select>
                    </div>
                    <div
                        class="flex min-h-[200px] items-end justify-around gap-3 pb-2"
                    >
                        <template
                            v-for="(item, i) in meilleursVentes.slice(0, 3)"
                            :key="item.id"
                        >
                            <div
                                class="flex min-w-0 max-w-[33%] flex-1 flex-col items-center gap-2"
                            >
                                <div
                                    class="flex w-full max-w-[80px] flex-col justify-end rounded-[50px] bg-[rgba(92,89,89,0.17)] p-1 sm:max-w-[96px]"
                                    style="height: 160px"
                                >
                                    <div
                                        class="w-full rounded-[50px] transition-all"
                                        :style="{
                                            height: `${Math.max(4, (item.ca / maxCa) * 100)}%`,
                                            minHeight:
                                                item.ca > 0 ? '12px' : '0',
                                            backgroundColor:
                                                barColors[i % barColors.length],
                                        }"
                                    />
                                </div>
                                <p
                                    class="line-clamp-2 max-w-full text-center text-[11px] font-medium text-black sm:text-xs"
                                >
                                    {{ item.designation }}
                                </p>
                                <p
                                    class="text-[11px] font-black text-black sm:text-xs"
                                >
                                    {{ item.ca.toLocaleString('fr-FR') }} XAF
                                </p>
                            </div>
                        </template>
                        <p
                            v-if="!meilleursVentes.length"
                            class="w-full py-10 text-center text-sm text-black/60"
                        >
                            Aucune vente sur cette période
                        </p>
                    </div>
                </div>
            </div>

            <!-- Évolution des crédits -->
            <div class="pharmacy-card mb-6 p-5 sm:p-6">
                <div
                    class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <h2
                            class="text-xl font-black text-black sm:text-2xl"
                        >
                            Évolution des crédits
                        </h2>
                        <p class="mt-1 max-w-3xl text-sm font-medium text-black/60">
                            Solde de crédits BengaDok après chaque opération
                            (recharges et déductions) sur la période affichée.
                        </p>
                    </div>
                    <select
                        v-model="periodSelectValue"
                        class="shrink-0 rounded-[13px] border border-black bg-white px-3 py-1.5 text-xs font-black focus:outline-none focus:ring-2 focus:ring-[#3995d2]/30"
                    >
                        <option value="month">Ce mois</option>
                        <option value="week">Cette semaine</option>
                    </select>
                </div>
                <div
                    class="flex min-h-[160px] items-end justify-between gap-1 overflow-x-auto pb-2 sm:gap-2"
                >
                    <template
                        v-for="(point, i) in creditsEvolutionParJour"
                        :key="i"
                    >
                        <div
                            class="flex min-w-[32px] flex-1 flex-col items-center gap-2 sm:min-w-[40px]"
                            :title="
                                `${point.label} : ${point.valeur.toLocaleString('fr-FR')} crédit(s)`
                            "
                        >
                            <div
                                class="flex h-[120px] w-full max-w-[64px] flex-col justify-end rounded-[50px] bg-[rgba(92,89,89,0.17)] p-1"
                            >
                                <div
                                    class="w-full rounded-[50px] bg-[#3995d2] transition-all"
                                    :style="{
                                        height: `${Math.max(
                                            2,
                                            maxCreditsEvolution > 0
                                                ? (point.valeur /
                                                      maxCreditsEvolution) *
                                                  100
                                                : 0,
                                        )}%`,
                                        minHeight:
                                            point.valeur > 0 ? '8px' : '0',
                                    }"
                                />
                            </div>
                            <span
                                class="text-[11px] font-medium text-black sm:text-xs"
                                >{{ point.label }}</span
                            >
                        </div>
                    </template>
                    <p
                        v-if="!creditsEvolutionParJour.length"
                        class="w-full py-8 text-center text-sm text-black/60"
                    >
                        Aucune donnée sur cette période
                    </p>
                </div>
            </div>

            <!-- Détail ventes + indisponibles -->
            <div class="grid gap-6 xl:grid-cols-2">
                <div class="pharmacy-card overflow-hidden p-5 sm:p-6">
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2 class="text-xl font-black text-black sm:text-2xl">
                            Détail des ventes (médicaments)
                        </h2>
                        <select
                            v-model="periodSelectValue"
                            class="rounded-[13px] border border-black bg-white px-3 py-1.5 text-xs font-black focus:outline-none focus:ring-2 focus:ring-[#3995d2]/30"
                        >
                            <option value="month">Ce mois</option>
                            <option value="week">Cette semaine</option>
                        </select>
                    </div>
                    <div class="overflow-x-auto">
                        <table
                            v-if="meilleursVentes.length"
                            class="w-full min-w-[320px] text-left text-sm"
                        >
                            <thead>
                                <tr
                                    class="border-b border-black/15 text-[11px] font-black uppercase text-black/50"
                                >
                                    <th class="pb-3 pr-2">Produit</th>
                                    <th class="pb-3 pr-2 text-right">Qté</th>
                                    <th class="pb-3 text-right">CA (XAF)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in meilleursVentes"
                                    :key="item.id"
                                    class="border-b border-black/10 last:border-0"
                                >
                                    <td
                                        class="py-2 pr-2 font-medium text-black"
                                    >
                                        {{ item.designation }}
                                    </td>
                                    <td
                                        class="py-2 pr-2 text-right tabular-nums"
                                    >
                                        {{
                                            Number(item.quantite).toLocaleString(
                                                'fr-FR',
                                                {
                                                    maximumFractionDigits: 2,
                                                },
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="py-2 text-right font-black tabular-nums text-black"
                                    >
                                        {{
                                            Math.round(item.ca).toLocaleString(
                                                'fr-FR',
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p
                            v-if="!meilleursVentes.length"
                            class="py-8 text-center text-sm text-black/60"
                        >
                            Aucune ligne de vente sur cette période
                        </p>
                    </div>
                </div>

                <div class="pharmacy-card overflow-hidden p-5 sm:p-6">
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2 class="text-xl font-black text-black sm:text-2xl">
                            Demandes indisponibles
                        </h2>
                        <select
                            v-model="periodSelectValue"
                            class="rounded-[13px] border border-black bg-white px-3 py-1.5 text-xs font-black focus:outline-none focus:ring-2 focus:ring-[#3995d2]/30"
                        >
                            <option value="month">Ce mois</option>
                            <option value="week">Cette semaine</option>
                        </select>
                    </div>
                    <p class="mb-4 text-xs font-medium text-black/55">
                        Médicaments marqués indisponibles sur au moins une ligne
                        de commande (période sélectionnée).
                    </p>
                    <div class="max-h-[340px] overflow-x-auto overflow-y-auto">
                        <table class="w-full min-w-[320px] text-left text-sm">
                            <thead class="sticky top-0 bg-white">
                                <tr
                                    class="border-b border-black/15 text-[11px] font-black uppercase text-black/50"
                                >
                                    <th class="pb-3 pr-2">Produit</th>
                                    <th class="pb-3 pr-2 text-right">
                                        Qté demandée
                                    </th>
                                    <th class="pb-3 text-right">
                                        Nb commandes
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in medicamentsIndisponibles"
                                    :key="`${row.id}-${row.dosage ?? ''}`"
                                    class="border-b border-black/10 last:border-0"
                                >
                                    <td class="py-2 pr-2 font-medium text-black">
                                        {{ row.designation }}
                                        <span
                                            v-if="row.dosage"
                                            class="block text-[11px] font-normal text-black/50"
                                        >
                                            {{ row.dosage }}
                                        </span>
                                    </td>
                                    <td class="py-2 pr-2 text-right tabular-nums">
                                        {{
                                            row.quantite_demandee.toLocaleString(
                                                'fr-FR',
                                            )
                                        }}
                                    </td>
                                    <td class="py-2 text-right tabular-nums">
                                        {{ row.nb_commandes }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p
                            v-if="!medicamentsIndisponibles.length"
                            class="py-8 text-center text-sm text-black/60"
                        >
                            Aucun médicament indisponible sur cette période
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </PharmacyLayout>
</template>
