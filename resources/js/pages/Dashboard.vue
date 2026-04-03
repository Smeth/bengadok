<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    ChevronDown,
    Pill,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { usePolling } from '@/composables/usePolling';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

usePolling();

const page = usePage();
const roles = computed(
    () =>
        (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? [],
);
const isPharma = computed(
    () => roles.value.includes('gerant') || roles.value.includes('vendeur'),
);

const periodDropdownOpen = ref(false);

const props = defineProps<{
    period?: string;
    kpis: {
        revenuTotal: number;
        nbPharmacies: number;
        nbCommandes: number;
        nbClients: number;
        evolutionRevenu: number;
        evolutionPharmacies: number;
        evolutionCommandes: number;
        evolutionClients: number;
    };
    volumeParPharmacie: Array<{
        pharmacie: { designation: string } | null;
        total: number;
    }>;
    volumeParZone: Array<{ zone_name: string; total: number }>;
    revenusParJour?: Array<{ jour: string; label: string; total: number }>;
}>();

const period = computed(() => props.period ?? 'month');

const kpiEvolutionHint = computed(() =>
    period.value === 'week' ? 'vs semaine précédente' : 'vs mois précédent',
);

function setPeriod(p: string) {
    periodDropdownOpen.value = false;
    router.get(dashboard(), { period: p }, { preserveState: true });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
];

// Couleurs des zones (design Figma)
const zoneColors = [
    '#3995D2', // Bleu - Moungali
    '#DC3545', // Rouge - Poto-Poto
    '#FD7E14', // Orange - Bacongo
    '#FFC107', // Jaune - Makélékélé
    '#198754', // Vert - Ouenzé
    '#8a38f5', // Violet - Mfilou (Figma)
];

const heroIcons = [
    {
        src: '/images/figma-assets/hero-icon-1.png',
        class: 'absolute right-[15%] -top-12 w-[140px] h-[140px] object-contain rotate-[4deg]',
    },
    {
        src: '/images/figma-assets/hero-icon-2.png',
        class: 'absolute right-[5%] top-2 w-[120px] h-[120px] object-contain -rotate-[17deg]',
    },
    {
        src: '/images/figma-assets/hero-icon-3.png',
        class: 'absolute right-[25%] top-4 w-[100px] h-[100px] object-contain rotate-[27deg]',
    },
    {
        src: '/images/figma-assets/hero-icon-4.png',
        class: 'absolute right-[12%] top-16 w-[130px] h-[130px] object-contain rotate-[12deg]',
    },
    {
        src: '/images/figma-assets/hero-icon-5.png',
        class: 'absolute right-[35%] -top-8 w-[180px] h-[180px] object-contain -rotate-[9deg]',
    },
    {
        src: '/images/figma-assets/hero-icon-6.png',
        class: 'absolute right-[20%] top-20 w-[150px] h-[150px] object-contain rotate-[10deg]',
    },
];

const volumeParZoneWithPercent = computed(() => {
    const total = props.volumeParZone.reduce((a, z) => a + z.total, 0) || 1;
    let currentAngle = 0;
    return props.volumeParZone.map((z, i) => {
        const percent = Math.round((z.total / total) * 100);
        const angle = (percent / 100) * 360;
        const startAngle = currentAngle;
        currentAngle += angle;
        return {
            ...z,
            percent,
            color: zoneColors[i % zoneColors.length],
            startAngle,
            angle,
        };
    });
});

const maxVolume = computed(() =>
    Math.max(...props.volumeParPharmacie.map((v) => v.total), 1),
);

const barChartScaleLabels = computed(() => {
    const max = maxVolume.value;
    if (max <= 0) return ['0'];
    const step = Math.max(1, Math.ceil(max / 5));
    const labels = [5 * step, 4 * step, 3 * step, 2 * step, step].filter(
        (v) => v <= max,
    );
    return labels.length > 0 ? labels : [max];
});

const revenusParJour = computed(() => props.revenusParJour ?? []);
const maxRevenuJour = computed(() =>
    Math.max(...revenusParJour.value.map((r) => r.total), 1),
);
const revenusScaleLabels = computed(() => {
    const max = maxRevenuJour.value;
    if (max <= 0) return ['0'];
    const step = Math.max(1, Math.ceil(max / 5));
    const labels = [5 * step, 4 * step, 3 * step, 2 * step, step].filter(
        (v) => v <= max,
    );
    return labels.length > 0 ? labels : [max];
});

function formatRevenuShort(val: number): string {
    if (val >= 1_000_000) return `${Math.round(val / 1_000_000)}M`;
    if (val >= 1_000) return `${Math.round(val / 1_000)}K`;
    return String(Math.round(val));
}

// Génère le path SVG pour un segment du diagramme circulaire (style Doughnut/Pie)
function getPiePath(
    cx: number,
    cy: number,
    radius: number,
    startAngle: number,
    endAngle: number,
    strokeWidth: number = 0,
) {
    const startRad = (startAngle * Math.PI) / 180;
    const endRad = (endAngle * Math.PI) / 180;

    // Si c'est un Doughnut (strokeWidth > 0), le calcul se fait sur le centre du trait
    const effectiveRadius = strokeWidth > 0 ? radius - strokeWidth / 2 : radius;

    const x1 = cx + effectiveRadius * Math.cos(startRad);
    const y1 = cy + effectiveRadius * Math.sin(startRad);
    const x2 = cx + effectiveRadius * Math.cos(endRad);
    const y2 = cy + effectiveRadius * Math.sin(endRad);

    const largeArc = endAngle - startAngle > 180 ? 1 : 0;

    // Si c'est un Doughnut (uniquement le trait, pas de remplissage)
    if (strokeWidth > 0) {
        return `M ${x1} ${y1} A ${effectiveRadius} ${effectiveRadius} 0 ${largeArc} 1 ${x2} ${y2}`;
    }

    // Sinon c'est un Pie classique (rempli jusqu'au centre)
    return `M ${cx} ${cy} L ${x1} ${y1} A ${effectiveRadius} ${effectiveRadius} 0 ${largeArc} 1 ${x2} ${y2} Z`;
}
</script>

<template>
    <Head title="Tableau de bord - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative min-h-full overflow-x-auto rounded-xl p-6 md:p-8">
            <!-- Hero section Admin - style Figma -->
            <div
                v-if="!isPharma"
                class="relative mb-6 overflow-hidden rounded-[30px] bg-white shadow-[0px_4px_10px_rgba(0,0,0,0.25)] min-h-[257px] flex items-center px-8 md:px-10 py-8"
            >
                <h1
                    class="relative z-20 max-w-[473px] text-[40px] md:text-[55px] font-extrabold leading-tight text-[#5c5959] drop-shadow-[4px_4px_20px_rgba(0,0,0,0.25)]"
                >
                    Toutes les données au même endroit
                </h1>
                <!-- Icônes 3D flottantes Figma -->
                <div
                    class="absolute inset-0 pointer-events-none hidden lg:block"
                >
                    <img
                        v-for="(icon, i) in heroIcons"
                        :key="i"
                        :src="icon.src"
                        :class="icon.class"
                        alt=""
                    />
                </div>
            </div>

            <!-- Hero section Pharma -->
            <div
                v-if="isPharma"
                class="relative mb-6 overflow-hidden rounded-[30px] bg-white p-8 shadow-[0px_4px_10px_rgba(0,0,0,0.25)] flex items-center justify-between min-h-[220px]"
            >
                <h1
                    class="relative z-10 max-w-[450px] text-[40px] font-bold leading-tight text-[#5c5959] md:text-[44px]"
                >
                    Créez de la proximité avec votre patientèle
                </h1>

                <div
                    class="absolute right-0 top-0 bottom-0 w-[60%] pointer-events-none hidden md:flex justify-end items-end gap-2 pr-8"
                >
                    <img
                        src="/images/pharma-banner-3.png"
                        class="h-[90%] object-contain -mb-2 z-20"
                        alt=""
                    />
                    <img
                        src="/images/pharma-banner-2.png"
                        class="h-full object-contain z-10"
                        alt=""
                    />
                </div>
            </div>

            <!-- Sélecteur de période global -->
            <div class="mb-4 flex justify-end">
                <div class="relative">
                    <button
                        class="flex items-center gap-2 rounded-[13px] border border-gray-300 bg-white px-4 py-2 text-[14px] font-semibold text-gray-800 shadow-sm hover:bg-gray-50"
                        @click="periodDropdownOpen = !periodDropdownOpen"
                    >
                        {{ period === 'week' ? 'Cette semaine' : 'Ce mois' }}
                        <ChevronDown class="size-4" />
                    </button>
                    <div
                        v-show="periodDropdownOpen"
                        class="absolute right-0 top-full z-20 mt-1 min-w-[160px] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                    >
                        <button
                            class="w-full px-4 py-2 text-left text-[14px] hover:bg-gray-100"
                            @click="setPeriod('week')"
                        >
                            Cette semaine
                        </button>
                        <button
                            class="w-full px-4 py-2 text-left text-[14px] hover:bg-gray-100"
                            @click="setPeriod('month')"
                        >
                            Ce mois
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats cards Admin - style Figma -->
            <div
                v-if="!isPharma"
                class="mb-6 grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    class="rounded-[23px] bg-white p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.25)] relative overflow-hidden group hover:shadow-lg transition-shadow"
                >
                    <h3 class="mb-6 text-[14px] font-extrabold text-[#5c5959]">
                        Revenue Total Comission
                    </h3>
                    <div class="mb-4 flex items-baseline gap-2">
                        <span class="text-[32px] font-extrabold text-black">{{
                            Number(kpis.revenuTotal).toLocaleString('fr-FR')
                        }}</span>
                        <span class="text-[14px] font-bold text-black"
                            >XAF</span
                        >
                    </div>
                    <div
                        class="flex items-center gap-2 text-[11px] font-semibold"
                    >
                        <span
                            class="flex items-center gap-1 rounded-full border border-black/20 px-2 py-1"
                            :class="
                                kpis.evolutionRevenu >= 0
                                    ? 'text-black'
                                    : 'text-red-600'
                            "
                        >
                            <ArrowUpRight
                                v-if="kpis.evolutionRevenu >= 0"
                                class="size-3"
                            />
                            <ArrowDownRight v-else class="size-3" />
                            {{ Math.abs(kpis.evolutionRevenu) }}%
                        </span>
                        <span class="text-[#5c5959]">{{
                            kpiEvolutionHint
                        }}</span>
                    </div>
                </div>

                <div
                    class="rounded-[23px] bg-white p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.25)] relative overflow-hidden group hover:shadow-lg transition-shadow"
                >
                    <h3 class="mb-6 text-[14px] font-extrabold text-[#5c5959]">
                        Pharmacies
                    </h3>
                    <div class="mb-4 flex items-baseline gap-2">
                        <span class="text-[32px] font-extrabold text-black">{{
                            kpis.nbPharmacies
                        }}</span>
                        <span class="text-[14px] font-bold text-black"
                            >Pharmacies de Jour</span
                        >
                    </div>
                    <div
                        class="flex items-center gap-2 text-[11px] font-semibold"
                    >
                        <span
                            class="flex items-center gap-1 rounded-full border border-black/20 px-2 py-1"
                            :class="
                                kpis.evolutionPharmacies >= 0
                                    ? 'text-black'
                                    : 'text-red-600'
                            "
                        >
                            <ArrowUpRight
                                v-if="kpis.evolutionPharmacies >= 0"
                                class="size-3"
                            />
                            <ArrowDownRight v-else class="size-3" />
                            {{ Math.abs(kpis.evolutionPharmacies) }}%
                        </span>
                        <span class="text-[#5c5959]">{{
                            kpiEvolutionHint
                        }}</span>
                    </div>
                </div>

                <div
                    class="rounded-[23px] bg-white p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.25)] relative overflow-hidden group hover:shadow-lg transition-shadow"
                >
                    <h3 class="mb-6 text-[14px] font-extrabold text-[#5c5959]">
                        Commandes Total
                    </h3>
                    <div class="mb-4 flex items-baseline gap-2">
                        <span class="text-[32px] font-extrabold text-black">{{
                            kpis.nbCommandes
                        }}</span>
                        <span class="text-[14px] font-bold text-black"
                            >cmd</span
                        >
                    </div>
                    <div
                        class="flex items-center gap-2 text-[11px] font-semibold"
                    >
                        <span
                            class="flex items-center gap-1 rounded-full border border-black/20 px-2 py-1"
                            :class="
                                kpis.evolutionCommandes >= 0
                                    ? 'text-black'
                                    : 'text-red-600'
                            "
                        >
                            <ArrowUpRight
                                v-if="kpis.evolutionCommandes >= 0"
                                class="size-3"
                            />
                            <ArrowDownRight v-else class="size-3" />
                            {{ Math.abs(kpis.evolutionCommandes) }}%
                        </span>
                        <span class="text-[#5c5959]">{{
                            kpiEvolutionHint
                        }}</span>
                    </div>
                </div>

                <div
                    class="rounded-[23px] bg-white p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.25)] relative overflow-hidden group hover:shadow-lg transition-shadow"
                >
                    <h3 class="mb-6 text-[14px] font-extrabold text-[#5c5959]">
                        Patientèles
                    </h3>
                    <div class="mb-4 flex items-baseline gap-2">
                        <span class="text-[32px] font-extrabold text-black">{{
                            kpis.nbClients
                        }}</span>
                    </div>
                    <div
                        class="flex items-center gap-2 text-[11px] font-semibold"
                    >
                        <span
                            class="flex items-center gap-1 rounded-full border border-black/20 px-2 py-1"
                            :class="
                                kpis.evolutionClients >= 0
                                    ? 'text-black'
                                    : 'text-red-600'
                            "
                        >
                            <ArrowUpRight
                                v-if="kpis.evolutionClients >= 0"
                                class="size-3"
                            />
                            <ArrowDownRight v-else class="size-3" />
                            {{ Math.abs(kpis.evolutionClients) }}%
                        </span>
                        <span class="text-[#5c5959]">{{
                            kpiEvolutionHint
                        }}</span>
                    </div>
                </div>
            </div>

            <!-- Stats cards Pharmacie -->
            <div
                v-if="isPharma"
                class="mb-6 grid gap-4 grid-cols-1 sm:grid-cols-3"
            >
                <div
                    class="rounded-[20px] bg-[#E1EFE8] backdrop-blur-sm p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.1)] relative transition-transform hover:-translate-y-1"
                >
                    <h3 class="mb-6 text-[15px] font-bold text-gray-900">
                        Revenue Total
                    </h3>
                    <div class="mb-4 flex items-baseline gap-2">
                        <span class="text-[36px] font-bold text-gray-900">{{
                            Number(kpis.revenuTotal).toLocaleString('fr-FR')
                        }}</span>
                        <span class="text-[14px] font-bold text-gray-900"
                            >XAF</span
                        >
                    </div>
                    <div
                        class="flex items-center gap-2 text-[11px] font-semibold"
                    >
                        <span
                            class="flex items-center gap-1 bg-white rounded-full px-2 py-1 shadow-sm"
                            :class="
                                kpis.evolutionRevenu >= 0
                                    ? 'text-black'
                                    : 'text-red-600'
                            "
                        >
                            <ArrowUpRight
                                v-if="kpis.evolutionRevenu >= 0"
                                class="size-3"
                            />
                            <ArrowDownRight v-else class="size-3" />
                            {{ Math.abs(kpis.evolutionRevenu) }}%
                        </span>
                        <span class="text-gray-800">{{
                            kpiEvolutionHint
                        }}</span>
                    </div>
                </div>

                <div
                    class="rounded-[20px] bg-[#E1EFE8] backdrop-blur-sm p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.1)] relative transition-transform hover:-translate-y-1"
                >
                    <h3 class="mb-6 text-[15px] font-bold text-gray-900">
                        Commande Total
                    </h3>
                    <div class="mb-4 flex items-baseline gap-2">
                        <span class="text-[36px] font-bold text-gray-900">{{
                            kpis.nbCommandes
                        }}</span>
                        <span class="text-[14px] font-bold text-gray-900"
                            >Cmd</span
                        >
                    </div>
                    <div
                        class="flex items-center gap-2 text-[11px] font-semibold"
                    >
                        <span
                            class="flex items-center gap-1 bg-white rounded-full px-2 py-1 shadow-sm"
                            :class="
                                kpis.evolutionCommandes >= 0
                                    ? 'text-black'
                                    : 'text-red-600'
                            "
                        >
                            <ArrowUpRight
                                v-if="kpis.evolutionCommandes >= 0"
                                class="size-3"
                            />
                            <ArrowDownRight v-else class="size-3" />
                            {{ Math.abs(kpis.evolutionCommandes) }}%
                        </span>
                        <span class="text-gray-800">{{
                            kpiEvolutionHint
                        }}</span>
                    </div>
                </div>

                <div
                    class="rounded-[20px] bg-[#E1EFE8] backdrop-blur-sm p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.1)] relative transition-transform hover:-translate-y-1"
                >
                    <h3 class="mb-6 text-[15px] font-bold text-gray-900">
                        Client Total
                    </h3>
                    <div class="mb-4 flex items-baseline gap-2">
                        <span class="text-[36px] font-bold text-gray-900">{{
                            kpis.nbClients
                        }}</span>
                        <span class="text-[14px] font-bold text-gray-900"
                            >Clients</span
                        >
                    </div>
                    <div
                        class="flex items-center gap-2 text-[11px] font-semibold"
                    >
                        <span
                            class="flex items-center gap-1 bg-white rounded-full px-2 py-1 shadow-sm"
                            :class="
                                kpis.evolutionClients >= 0
                                    ? 'text-black'
                                    : 'text-red-600'
                            "
                        >
                            <ArrowUpRight
                                v-if="kpis.evolutionClients >= 0"
                                class="size-3"
                            />
                            <ArrowDownRight v-else class="size-3" />
                            {{ Math.abs(kpis.evolutionClients) }}%
                        </span>
                        <span class="text-gray-800">{{
                            kpiEvolutionHint
                        }}</span>
                    </div>
                </div>
            </div>

            <!-- Charts Admin - style Figma -->
            <div v-if="!isPharma" class="grid gap-6 lg:grid-cols-2">
                <!-- Volume commandes par pharmacies (bar chart) -->
                <div
                    class="rounded-[30px] bg-white p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.25)]"
                >
                    <div class="mb-6 flex items-center justify-between">
                        <h3 class="text-[20px] font-bold text-black">
                            Volume commandes par pharmacies
                        </h3>
                        <span
                            class="rounded-[13px] border border-black/30 bg-gray-50 px-3 py-1 text-[14px] font-semibold text-gray-700"
                        >
                            {{
                                period === 'week' ? 'Cette semaine' : 'Ce mois'
                            }}
                        </span>
                    </div>
                    <div
                        class="relative flex items-end justify-around gap-3 px-4"
                        style="height: 300px"
                    >
                        <div
                            class="absolute left-0 top-0 flex flex-col justify-between text-[12px] text-gray-600"
                            style="bottom: 2rem"
                        >
                            <span
                                v-for="label in barChartScaleLabels"
                                :key="label"
                                >{{ label }}</span
                            >
                        </div>
                        <div
                            class="ml-8 flex flex-1 items-end justify-around gap-3"
                        >
                            <div
                                v-for="(item, i) in volumeParPharmacie"
                                :key="i"
                                class="flex flex-1 flex-col items-center"
                            >
                                <div
                                    class="w-3 rounded-t-[3px] bg-[#5bb66e] transition-all hover:bg-[#4ca55d] min-w-[12px]"
                                    :style="{
                                        height: `${
                                            (item.total / maxVolume) * 250
                                        }px`,
                                        minHeight: item.total > 0 ? '8px' : '0',
                                    }"
                                />
                                <span
                                    class="mt-2 w-full truncate whitespace-nowrap text-center text-[10px] text-gray-700"
                                    :title="item.pharmacie?.designation"
                                >
                                    {{ item.pharmacie?.designation ?? 'N/A' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Volume commandes par zone (pie chart) -->
                <div
                    class="rounded-[30px] bg-white p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.25)]"
                >
                    <div class="mb-6 flex items-center justify-between">
                        <h3 class="text-[20px] font-bold text-black">
                            Volume commandes par zone
                        </h3>
                        <span
                            class="rounded-[13px] border border-black/30 bg-gray-50 px-3 py-1 text-[14px] font-semibold text-gray-700"
                        >
                            {{
                                period === 'week' ? 'Cette semaine' : 'Ce mois'
                            }}
                        </span>
                    </div>

                    <div
                        class="flex flex-col lg:flex-row items-center lg:items-start gap-6 mt-6"
                    >
                        <!-- Graphique donut à gauche -->
                        <div class="relative size-[180px] shrink-0">
                            <svg
                                viewBox="0 0 200 200"
                                class="size-full -rotate-90"
                            >
                                <circle
                                    cx="100"
                                    cy="100"
                                    r="75"
                                    fill="none"
                                    stroke="#f3f4f6"
                                    stroke-width="25"
                                />
                                <path
                                    v-for="zone in volumeParZoneWithPercent"
                                    v-show="zone.percent > 0"
                                    :key="zone.zone_name"
                                    :d="
                                        getPiePath(
                                            100,
                                            100,
                                            100,
                                            zone.startAngle,
                                            zone.startAngle + zone.angle,
                                            25,
                                        )
                                    "
                                    fill="none"
                                    :stroke="zone.color"
                                    stroke-width="25"
                                    class="transition-opacity hover:opacity-80"
                                />
                            </svg>
                            <div
                                class="absolute inset-0 flex flex-col items-center justify-center"
                            >
                                <div
                                    class="flex size-[54px] items-center justify-center rounded-full bg-[#f3f4f6]"
                                >
                                    <Pill class="size-6 text-[#3995D2]" />
                                </div>
                            </div>
                        </div>
                        <!-- Légende avec barres de progression à droite -->
                        <div class="flex flex-1 flex-col gap-4 min-w-0">
                            <div
                                v-for="zone in volumeParZoneWithPercent"
                                :key="zone.zone_name"
                                class="flex items-center gap-3 w-full"
                            >
                                <span
                                    class="text-[13px] font-bold truncate shrink-0"
                                    :style="{ color: zone.color }"
                                >
                                    {{ zone.zone_name }}
                                </span>
                                <div
                                    class="flex-1 h-[5px] min-w-0 rounded-full bg-[rgba(102,102,102,0.42)] overflow-hidden"
                                >
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :style="{
                                            width: `${zone.percent}%`,
                                            backgroundColor: zone.color,
                                        }"
                                    />
                                </div>
                                <span
                                    class="text-[13px] font-bold shrink-0"
                                    :style="{ color: zone.color }"
                                >
                                    {{ zone.percent }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Pharmacie -->
            <div v-if="isPharma">
                <div
                    class="rounded-[30px] bg-[#E1EFE8] backdrop-blur-sm p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.1)] w-full relative"
                >
                    <div class="mb-6 flex items-center justify-between">
                        <h3 class="text-[20px] font-extrabold text-gray-900">
                            Analyse revenues
                        </h3>
                        <span
                            class="rounded-full border border-gray-400 bg-white/80 px-4 py-1.5 text-[13px] font-bold text-gray-900"
                        >
                            {{
                                period === 'week' ? 'Cette semaine' : 'Ce mois'
                            }}
                        </span>
                    </div>
                    <!-- Chart barres revenues -->
                    <div
                        class="flex items-end px-2 pt-2 gap-2 lg:gap-4 relative h-[250px] mt-4 w-full"
                    >
                        <div
                            class="flex flex-col justify-between text-[11px] font-bold text-gray-600 h-full pb-6"
                        >
                            <span
                                v-for="label in revenusScaleLabels"
                                :key="label"
                                >{{ formatRevenuShort(label) }}</span
                            >
                        </div>

                        <!-- Barres -->
                        <div
                            class="flex flex-1 items-end justify-between ml-4 h-[250px] pb-[1.75rem]"
                        >
                            <div
                                v-for="item in revenusParJour"
                                :key="item.jour"
                                class="flex flex-col items-center flex-1 mx-1 gap-2 h-full justify-end"
                            >
                                <div
                                    class="relative w-full max-w-[50px] h-full bg-[#d0e6d9] rounded-[24px] overflow-hidden flex items-end opacity-80 group cursor-pointer"
                                >
                                    <div
                                        class="w-full bg-[#5BB66E] rounded-[24px] transition-all duration-300 group-hover:bg-[#4a975a]"
                                        :style="{
                                            height: `${((item.total || 0) / (maxRevenuJour || 1)) * 100}%`,
                                        }"
                                    />
                                </div>
                                <span
                                    class="text-[13px] font-bold text-gray-700 mt-2"
                                    >{{ item.label }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
