<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    DollarSign,
    Package,
    Search,
    Users,
    XCircle,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

defineProps<{
    kpis: {
        revenuTotal: number;
        nbPharmacies: number;
        nbCommandes: number;
        nbClients: number;
    };
    volumeParPharmacie: Array<{ pharmacie: { designation: string } | null; total: number }>;
    volumeParZone: Array<{ zone_name: string; total: number }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dok Board', href: dashboard() },
];
</script>

<template>
    <Head title="Dok Board - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <div>
                <h1 class="text-2xl font-semibold text-sidebar-foreground">
                    Toutes les données au même endroit
                </h1>
                <p class="mt-1 flex items-center gap-2 text-muted-foreground">
                    <CheckCircle2 class="size-4" />
                    <Search class="size-4" />
                    <Users class="size-4" />
                    <XCircle class="size-4" />
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-sidebar p-4 dark:border-sidebar-border"
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
                    <p class="text-xs text-muted-foreground">Depuis cette semaine</p>
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-sidebar p-4 dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-2">
                        <Building2 class="size-5 text-blue-500" />
                        <span class="text-sm font-medium text-muted-foreground">
                            Pharmacies
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold">{{ kpis.nbPharmacies }}</p>
                    <p class="text-xs text-muted-foreground">Pharmacies de Jour</p>
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-sidebar p-4 dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-2">
                        <Package class="size-5 text-amber-500" />
                        <span class="text-sm font-medium text-muted-foreground">
                            Commandes Total
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold">{{ kpis.nbCommandes }} cmd</p>
                    <p class="text-xs text-muted-foreground">Depuis cette semaine</p>
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-sidebar p-4 dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-2">
                        <Users class="size-5 text-violet-500" />
                        <span class="text-sm font-medium text-muted-foreground">
                            Patientèles
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold">{{ kpis.nbClients }}</p>
                    <p class="text-xs text-muted-foreground">Depuis cette semaine</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-sidebar p-4 dark:border-sidebar-border"
                >
                    <h3 class="mb-4 font-semibold">Volume commandes par pharmacies</h3>
                    <div class="space-y-3">
                        <div
                            v-for="(item, i) in volumeParPharmacie"
                            :key="i"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="w-32 truncate text-sm"
                                :title="item.pharmacie?.designation"
                            >
                                {{ item.pharmacie?.designation ?? 'N/A' }}
                            </span>
                            <div class="flex-1">
                                <div
                                    class="h-6 rounded bg-blue-500/30"
                                    :style="{
                                        width: `${
                                            (item.total /
                                                Math.max(
                                                    ...volumeParPharmacie.map((v) => v.total),
                                                    1
                                                )) *
                                            100
                                        }%`,
                                    }"
                                />
                            </div>
                            <span class="text-sm font-medium">{{ item.total }}</span>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-sidebar p-4 dark:border-sidebar-border"
                >
                    <h3 class="mb-4 font-semibold">Volume commandes par zone</h3>
                    <div class="flex flex-wrap gap-4">
                        <div
                            v-for="(zone, i) in volumeParZone"
                            :key="i"
                            class="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2"
                        >
                            <div
                                class="size-3 rounded-full"
                                :style="{
                                    backgroundColor: `hsl(${(i * 60) % 360}, 70%, 50%)`,
                                }"
                            />
                            <span class="text-sm">{{ zone.zone_name }}</span>
                            <span class="text-sm font-semibold">
                                {{ Math.round(
                                    (zone.total /
                                        (volumeParZone.reduce((a, z) => a + z.total, 0) || 1)) *
                                        100
                                ) }}%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
