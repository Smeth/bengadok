<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    produit: {
        id: number;
        designation: string;
        dosage?: string;
        forme?: string;
        type: string;
        created_at?: string;
        ventes: number;
        ca: number;
        classement?: number | null;
        prix_moyen: number;
        prix_min: number;
        prix_max: number;
        ecart: number;
        ecart_pct: number;
        comparaison: Array<{
            id: number;
            designation: string;
            prix: number;
            plus_bas: boolean;
            plus_eleve: boolean;
        }>;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Médicaments', href: '/medicaments' },
    { title: props.produit.designation + (props.produit.dosage ? ' ' + props.produit.dosage : ''), href: '#' },
];

function designationComplete() {
    return [props.produit.designation, props.produit.dosage].filter(Boolean).join(' ');
}
</script>

<template>
    <Head :title="`${designationComplete()} - BengaDok`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6"
            style="background: linear-gradient(to right, rgba(91, 176, 52, 0.14) 0%, rgba(52, 176, 199, 0.14) 100%);"
        >
            <!-- Tabs (same as Index) -->
            <div class="flex gap-2">
                <button
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="'bg-white/80 text-muted-foreground'"
                >
                    Catalogue Médicaments
                </button>
                <button
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="'bg-white/80 text-muted-foreground'"
                >
                    Statistiques
                </button>
            </div>

            <Button variant="ghost" size="sm" as-child>
                <Link href="/medicaments" class="flex items-center gap-1">
                    <ArrowLeft class="size-4" />
                    Retour à la liste
                </Link>
            </Button>

            <h1 class="text-2xl font-semibold">Détail Produit</h1>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left: Produit card + stats -->
                <div class="space-y-4 lg:col-span-1">
                    <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95">
                        <h2 class="mb-3 text-lg font-semibold">{{ designationComplete() }}</h2>
                        <span class="inline-block rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-800 dark:bg-blue-900/30">
                            {{ produit.type || 'Vente libre' }}
                        </span>
                        <div class="mt-4 space-y-2 text-sm">
                            <p><span class="text-muted-foreground">Dosage:</span> {{ produit.dosage || '-' }}</p>
                            <p><span class="text-muted-foreground">Forme:</span> {{ produit.forme || '-' }}</p>
                            <p><span class="text-muted-foreground">Ajouté le:</span> {{ produit.created_at || '-' }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                        <div class="rounded-xl border bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/95">
                            <p class="text-sm text-muted-foreground">Ventes totales</p>
                            <p class="text-xl font-bold text-emerald-600">{{ produit.ventes }}</p>
                        </div>
                        <div class="rounded-xl border bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/95">
                            <p class="text-sm text-muted-foreground">CA généré</p>
                            <p class="text-xl font-bold text-amber-600">{{ Number(produit.ca).toLocaleString('fr-FR') }} xaf</p>
                        </div>
                        <div class="rounded-xl border bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/95">
                            <p class="text-sm text-muted-foreground">Popularité</p>
                            <p class="text-xl font-bold text-red-600">
                                {{ produit.classement ? `#${produit.classement} Classement` : '-' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right: Comparaison des prix -->
                <div class="lg:col-span-2">
                    <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95">
                        <h3 class="mb-4 font-semibold">Comparaison des prix par pharmacie</h3>
                        <div class="space-y-2">
                            <div
                                v-for="ph in produit.comparaison"
                                :key="ph.id"
                                class="flex items-center justify-between rounded-lg border p-3"
                                :class="{
                                    'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-900/10': ph.plus_bas,
                                    'border-red-500 bg-red-50/50 dark:bg-red-900/10': ph.plus_eleve,
                                    'border-input': !ph.plus_bas && !ph.plus_eleve,
                                }"
                            >
                                <span class="font-medium">{{ ph.designation }}</span>
                                <div class="flex items-center gap-2">
                                    <span>{{ Number(ph.prix).toLocaleString('fr-FR') }} xaf</span>
                                    <span
                                        v-if="ph.plus_bas"
                                        class="rounded-full bg-blue-500 px-2 py-0.5 text-xs text-white"
                                    >
                                        Le prix le plus bas
                                    </span>
                                    <span
                                        v-if="ph.plus_eleve"
                                        class="rounded-full bg-red-500 px-2 py-0.5 text-xs text-white"
                                    >
                                        Le prix le plus élevé
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-2 border-t pt-4 text-sm">
                            <p class="flex justify-between">
                                <span class="text-muted-foreground">Prix moyen</span>
                                <strong class="text-blue-600">{{ Number(produit.prix_moyen).toLocaleString('fr-FR') }} xaf</strong>
                            </p>
                            <p class="flex justify-between">
                                <span class="text-muted-foreground">Prix minimum</span>
                                <strong class="text-amber-600">{{ Number(produit.prix_min).toLocaleString('fr-FR') }} xaf</strong>
                            </p>
                            <p class="flex justify-between">
                                <span class="text-muted-foreground">Prix maximum</span>
                                <strong class="text-emerald-600">{{ Number(produit.prix_max).toLocaleString('fr-FR') }} xaf</strong>
                            </p>
                            <p class="flex justify-between">
                                <span class="text-muted-foreground">Ecart de prix</span>
                                <strong>{{ Number(produit.ecart).toLocaleString('fr-FR') }} xaf ({{ produit.ecart_pct }}%)</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
