<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    User,
    Phone,
    MapPin,
    Calendar,
    Users,
    TrendingUp,
    RefreshCw,
    Pencil,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import { clientNomComplet } from '@/lib/clientDisplayName';

const props = defineProps<{
    client: {
        id: number;
        nom: string | null;
        prenom: string | null;
        tel: string;
        tel_secondaire?: string;
        adresse: string;
        zone?: string;
        client_depuis: string;
        derniere_commande: string;
        nb_commandes: number;
        total_depense: number;
        panier_moyen: number;
        habitué: boolean;
        pour_soi: number;
        pour_tiers: number;
        pct_soi: number;
        pct_tiers: number;
        categories_tiers: string[];
        tiers_frequent: string;
        medicaments_frequents: string[];
    };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Clients', href: '/clients' },
    { title: clientNomComplet(props.client), href: `/clients/${props.client.id}` },
]);

function nomComplet() {
    return clientNomComplet(props.client);
}
</script>

<template>
    <Head :title="`Profil Client - ${nomComplet()} - BengaDok`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6"
            style="background: linear-gradient(to right, rgba(91, 176, 52, 0.14) 0%, rgba(52, 176, 199, 0.14) 100%);"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Button variant="link" class="w-fit -ml-2" as-child>
                        <Link href="/clients" class="flex items-center gap-2 text-[#459cd1]">
                            <ArrowLeft class="size-4" />
                            Retour à la liste
                        </Link>
                    </Button>
                    <h1 class="text-2xl font-bold text-foreground">Profil Client</h1>
                </div>
                <Button class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]">
                    <Pencil class="mr-2 size-4" />
                    Enrichir le profil
                </Button>
            </div>

            <!-- Tabs (comme sur la liste) -->
            <div class="flex gap-2 border-b border-slate-200 pb-2">
                <Link
                    href="/clients"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors bg-[#459cd1] text-white"
                >
                    Liste des clients
                </Link>
                <Link
                    href="/clients?tab=doublons"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-white/80"
                >
                    Gestion des doublons Clients
                </Link>
                <Link
                    href="/clients?tab=statistiques"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-white/80"
                >
                    Statistiques
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Carte gauche: Infos personnelles -->
                <div class="rounded-xl border border-white/80 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/95">
                    <div class="mb-4 flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-sky-100 text-sky-600">
                                <User class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-foreground">{{ nomComplet() }}</h2>
                                <span
                                    v-if="client.habitué"
                                    class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-900/30"
                                >
                                    Habitué
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 text-sm">
                        <p class="flex items-center gap-2">
                            <Phone class="size-4 shrink-0 text-sky-600" />
                            {{ client.tel }}
                        </p>
                        <p v-if="client.tel_secondaire" class="flex items-center gap-2">
                            <Phone class="size-4 shrink-0 text-sky-600" />
                            <span class="text-muted-foreground">Tél. secondaire:</span> {{ client.tel_secondaire }}
                        </p>
                        <p class="flex items-center gap-2">
                            <MapPin class="size-4 shrink-0 text-sky-600" />
                            {{ client.adresse || '-' }}
                        </p>
                        <p v-if="client.zone">
                            <span class="text-muted-foreground">Zone/Arrondissement:</span>
                            <span class="ml-1 font-medium text-[#459cd1]">{{ client.zone }}, Brazzaville</span>
                        </p>
                        <p v-else class="text-muted-foreground">
                            Zone/Arrondissement: -
                        </p>
                        <p class="flex items-center gap-2">
                            <Calendar class="size-4 shrink-0 text-sky-600" />
                            <span class="text-muted-foreground">Client depuis</span>
                            {{ client.client_depuis || '-' }}
                        </p>
                        <p class="flex items-center gap-2">
                            <Calendar class="size-4 shrink-0 text-sky-600" />
                            <span class="text-muted-foreground">Dernière commande</span>
                            {{ client.derniere_commande || '-' }}
                        </p>
                        <p class="flex items-center gap-2 text-muted-foreground">
                            <Users class="size-4 shrink-0" />
                            Niche(s): -
                        </p>
                        <p class="flex items-center gap-2 text-muted-foreground">
                            <TrendingUp class="size-4 shrink-0" />
                            Canal d'acquisition: -
                        </p>
                    </div>
                </div>

                <!-- Carte droite: Activité et habitudes -->
                <div class="rounded-xl border border-white/80 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/95">
                    <!-- Métriques -->
                    <div class="mb-6 grid grid-cols-3 gap-3">
                        <div class="rounded-lg bg-emerald-500 p-3 text-center text-white">
                            <p class="text-xs opacity-90">Commandes</p>
                            <p class="text-xl font-bold">{{ client.nb_commandes }}</p>
                        </div>
                        <div class="rounded-lg bg-amber-500 p-3 text-center text-white">
                            <p class="text-xs opacity-90">Total dépensé</p>
                            <p class="text-lg font-bold">{{ Number(client.total_depense).toLocaleString('fr-FR') }} xaf</p>
                        </div>
                        <div class="rounded-lg bg-orange-500 p-3 text-center text-white">
                            <p class="text-xs opacity-90">Panier moyen</p>
                            <p class="text-lg font-bold">{{ Number(client.panier_moyen).toLocaleString('fr-FR') }} xaf</p>
                        </div>
                    </div>

                    <!-- Habitudes de commande -->
                    <div>
                        <h3 class="mb-3 flex items-center gap-2 font-semibold text-foreground">
                            <RefreshCw class="size-4" />
                            Habitudes de commande
                        </h3>
                        <div class="mb-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg border-2 border-[#459cd1] p-3 text-center">
                                <p class="text-xl font-bold text-[#459cd1]">{{ client.pour_soi }}</p>
                                <p class="text-sm text-muted-foreground">Commandes pour soi</p>
                                <p class="text-xs text-muted-foreground">{{ client.pct_soi }}% du total</p>
                            </div>
                            <div class="rounded-lg border-2 border-[#459cd1] p-3 text-center">
                                <p class="text-xl font-bold text-[#459cd1]">{{ client.pour_tiers }}</p>
                                <p class="text-sm text-muted-foreground">Commandes pour tiers</p>
                                <p class="text-xs text-muted-foreground">{{ client.pct_tiers }}% du total</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div v-if="client.categories_tiers?.length">
                                <p class="mb-1 text-xs font-medium text-muted-foreground">Catégories des tiers</p>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="cat in client.categories_tiers"
                                        :key="cat"
                                        class="rounded-full bg-slate-100 px-3 py-1 text-sm dark:bg-slate-800"
                                    >
                                        {{ cat }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="client.tiers_frequent && client.tiers_frequent !== '-'">
                                <p class="mb-1 text-xs font-medium text-muted-foreground">Tiers les plus fréquents</p>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm dark:bg-slate-800">
                                    {{ client.tiers_frequent }}
                                </span>
                            </div>
                            <div v-if="client.medicaments_frequents?.length">
                                <p class="mb-1 text-xs font-medium text-muted-foreground">Médicaments fréquents</p>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="med in client.medicaments_frequents"
                                        :key="med"
                                        class="rounded-full bg-slate-100 px-3 py-1 text-sm dark:bg-slate-800"
                                    >
                                        {{ med }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
