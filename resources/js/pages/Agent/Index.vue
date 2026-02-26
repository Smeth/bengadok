<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

defineProps<{
    commandes: {
        data: Array<{
            id: number;
            numero: string;
            date: string;
            status: string;
            prix_total: number;
            client: { nom: string; prenom: string; tel: string };
            pharmacie: { designation: string };
        }>;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dok Board', href: dashboard() },
    { title: 'Mes réceptions', href: '/agent' },
];
</script>

<template>
    <Head title="Agent - Réceptions - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Mes réceptions</h1>
                <Link href="/agent/nouvelle-commande">
                    <button
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        Nouvelle commande
                    </button>
                </Link>
            </div>

            <div class="rounded-xl border overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">N°</th>
                            <th class="px-4 py-3 text-left font-medium">Client</th>
                            <th class="px-4 py-3 text-left font-medium">Pharmacie</th>
                            <th class="px-4 py-3 text-left font-medium">Date</th>
                            <th class="px-4 py-3 text-left font-medium">Montant</th>
                            <th class="px-4 py-3 text-left font-medium">Statut</th>
                            <th class="px-4 py-3 text-left font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="cmd in commandes.data"
                            :key="cmd.id"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-mono">{{ cmd.numero }}</td>
                            <td class="px-4 py-3">{{ cmd.client?.prenom }} {{ cmd.client?.nom }} - {{ cmd.client?.tel }}</td>
                            <td class="px-4 py-3">{{ cmd.pharmacie?.designation }}</td>
                            <td class="px-4 py-3">{{ cmd.date }}</td>
                            <td class="px-4 py-3">{{ Number(cmd.prix_total).toLocaleString('fr-FR') }} XAF</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="{
                                    'bg-blue-100 dark:bg-blue-900/30': cmd.status === 'nouvelle',
                                    'bg-emerald-100 dark:bg-emerald-900/30': cmd.status === 'validee' || cmd.status === 'livree',
                                }">
                                    {{ cmd.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="`/commandes/${cmd.id}`" class="text-primary hover:underline">Détails</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
