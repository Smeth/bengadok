<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { usePolling } from '@/composables/usePolling';
import {
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    Building2,
    CheckCircle2,
    DollarSign,
    Package,
    Pill,
    Users,
    XCircle,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

usePolling();

const props = defineProps<{
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
    volumeParPharmacie: Array<{ pharmacie: { designation: string } | null; total: number }>;
    volumeParZone: Array<{ zone_name: string; total: number }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
];

// Image 3 : Moungali bleu, Poto-Poto rouge, Bacongo orange, Makélékélé jaune, Ouenzé vert foncé, Mfilou violet
const zoneColors = [
    '#3b82f6',  // Bleu - Moungali
    '#ef4444',  // Rouge - Poto-Poto
    '#f97316',  // Orange - Bacongo
    '#eab308',  // Jaune - Makélékélé
    '#15803d',  // Vert foncé - Ouenzé
    '#8b5cf6',  // Violet - Mfilou
];

const volumeParZoneWithPercent = computed(() => {
    const total = props.volumeParZone.reduce((a, z) => a + z.total, 0) || 1;
    const PI2 = 2 * Math.PI * 50;
    let offset = 0;
    return props.volumeParZone.map((z, i) => {
        const percent = Math.round((z.total / total) * 100);
        const dashLength = (percent / 100) * PI2;
        const item = {
            ...z,
            percent,
            color: zoneColors[i % zoneColors.length],
            dashArray: `${dashLength} ${PI2}`,
            dashOffset: -offset,
        };
        offset += dashLength;
        return item;
    });
});

const maxVolume = computed(() =>
    Math.max(...props.volumeParPharmacie.map((v) => v.total), 1)
);
</script>

<template>
    <Head title="Tableau de bord - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6"
            style="background: linear-gradient(to right, rgba(91, 176, 52, 0.14) 0%, rgba(52, 176, 199, 0.14) 100%);"
        >
            <div>
                <h1
                    class="flex flex-wrap items-center gap-2 text-2xl font-bold text-foreground md:text-3xl"
                >
                    Toutes les données au même endroit
                    <CheckCircle2 class="size-6 text-emerald-500" />
                    <span class="opacity-60">|</span>
                    <Users class="size-5 text-muted-foreground" />
                    <XCircle class="size-5 text-red-500" />
                    <Package class="size-5 text-amber-500" />
                    <BarChart3 class="size-5 text-blue-500" />
                </h1>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="rounded-xl border border-white/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95"
                >
                    <div class="flex items-center gap-2">
                        <DollarSign class="size-5 text-emerald-500" />
                        <span class="text-sm font-medium text-muted-foreground">
                            Revenue Total Commission
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold">
                        {{ Number(kpis.revenuTotal).toLocaleString('fr-FR') }} XAF
                    </p>
                    <p
                        class="mt-1 flex items-center gap-1 text-xs"
                        :class="
                            kpis.evolutionRevenu >= 0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-red-600 dark:text-red-400'
                        "
                    >
                        <ArrowUpRight
                            v-if="kpis.evolutionRevenu >= 0"
                            class="size-3.5"
                        />
                        <ArrowDownRight v-else class="size-3.5" />
                        {{ Math.abs(kpis.evolutionRevenu) }}% Depuis cette
                        semaine.
                    </p>
                </div>

                <div
                    class="rounded-xl border border-white/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95"
                >
                    <div class="flex items-center gap-2">
                        <Building2 class="size-5 text-blue-500" />
                        <span class="text-sm font-medium text-muted-foreground">
                            Pharmacies
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold">
                        {{ kpis.nbPharmacies }} Pharmacies de Jour
                    </p>
                    <p
                        class="mt-1 flex items-center gap-1 text-xs"
                        :class="
                            kpis.evolutionPharmacies >= 0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-red-600 dark:text-red-400'
                        "
                    >
                        <ArrowUpRight
                            v-if="kpis.evolutionPharmacies >= 0"
                            class="size-3.5"
                        />
                        <ArrowDownRight v-else class="size-3.5" />
                        {{ Math.abs(kpis.evolutionPharmacies) }}% Depuis cette
                        semaine.
                    </p>
                </div>

                <div
                    class="rounded-xl border border-white/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95"
                >
                    <div class="flex items-center gap-2">
                        <Package class="size-5 text-amber-500" />
                        <span class="text-sm font-medium text-muted-foreground">
                            Commandes Total
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold">
                        {{ kpis.nbCommandes }} cmd
                    </p>
                    <p
                        class="mt-1 flex items-center gap-1 text-xs"
                        :class="
                            kpis.evolutionCommandes >= 0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-red-600 dark:text-red-400'
                        "
                    >
                        <ArrowUpRight
                            v-if="kpis.evolutionCommandes >= 0"
                            class="size-3.5"
                        />
                        <ArrowDownRight v-else class="size-3.5" />
                        {{ Math.abs(kpis.evolutionCommandes) }}% Depuis cette
                        semaine.
                    </p>
                </div>

                <div
                    class="rounded-xl border border-white/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95"
                >
                    <div class="flex items-center gap-2">
                        <Users class="size-5 text-violet-500" />
                        <span class="text-sm font-medium text-muted-foreground">
                            Patientèles
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold">{{ kpis.nbClients }}</p>
                    <p
                        class="mt-1 flex items-center gap-1 text-xs"
                        :class="
                            kpis.evolutionClients >= 0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-red-600 dark:text-red-400'
                        "
                    >
                        <ArrowUpRight
                            v-if="kpis.evolutionClients >= 0"
                            class="size-3.5"
                        />
                        <ArrowDownRight v-else class="size-3.5" />
                        {{ Math.abs(kpis.evolutionClients) }}% Depuis cette
                        semaine.
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div
                    class="rounded-xl border border-white/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold">
                            Volume commandes par pharmacies
                        </h3>
                        <select
                            class="rounded-md border border-input bg-background px-2 py-1 text-sm"
                        >
                            <option>Ce mois</option>
                        </select>
                    </div>
                    <div
                        class="flex items-end justify-around gap-2 pt-4"
                        style="height: 220px"
                    >
                        <div
                            v-for="(item, i) in volumeParPharmacie"
                            :key="i"
                            class="flex flex-1 flex-col items-center gap-1"
                        >
                            <div
                                class="w-full rounded-t bg-blue-500 transition-all"
                                :style="{
                                    height: `${
                                        (item.total / maxVolume) * 180
                                    }px`,
                                    minHeight: item.total > 0 ? '8px' : '0',
                                }"
                            />
                            <span
                                class="w-full truncate text-center text-xs text-muted-foreground"
                                :title="item.pharmacie?.designation"
                            >
                                {{ item.pharmacie?.designation ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-white/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold">
                            Volume commandes par zone
                        </h3>
                        <select
                            class="rounded-md border border-input bg-background px-2 py-1 text-sm"
                        >
                            <option>Ce mois</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-center gap-8">
                        <div class="relative">
                            <svg
                                viewBox="0 0 120 120"
                                class="size-40 -rotate-90"
                            >
                                <circle
                                    cx="60"
                                    cy="60"
                                    r="50"
                                    fill="none"
                                    stroke="#e5e7eb"
                                    stroke-width="16"
                                />
                                <circle
                                    v-for="zone in volumeParZoneWithPercent"
                                    v-show="zone.percent > 0"
                                    :key="zone.zone_name"
                                    cx="60"
                                    cy="60"
                                    r="50"
                                    fill="none"
                                    :stroke="zone.color"
                                    stroke-width="16"
                                    :stroke-dasharray="zone.dashArray"
                                    :stroke-dashoffset="zone.dashOffset"
                                />
                            </svg>
                            <div
                                class="absolute inset-0 flex flex-col items-center justify-center"
                            >
                                <Pill class="size-8 text-muted-foreground" />
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="(zone, i) in volumeParZoneWithPercent"
                                :key="zone.zone_name"
                                class="flex items-center gap-2"
                            >
                                <div
                                    class="size-3 shrink-0 rounded-full"
                                    :style="{
                                        backgroundColor: zone.color,
                                    }"
                                />
                                <span class="text-sm">{{ zone.zone_name }}</span>
                                <span class="text-sm font-semibold">
                                    {{ zone.percent }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
