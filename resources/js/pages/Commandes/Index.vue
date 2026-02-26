<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal, Search } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

const props = defineProps<{
    commandes: {
        data: Array<{
            id: number;
            numero: string;
            date: string;
            status: string;
            prix_total: number;
            client: { nom: string; prenom: string; tel: string; adresse?: string; sexe?: string };
            produits: Array<{ designation: string; pivot: { quantite: number } }>;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    stats: Record<string, number>;
    filters: { search?: string; status?: string; periode?: string };
}>();

const searchQuery = ref(props.filters.search ?? '');
watch(() => props.filters.search, (v) => { searchQuery.value = v ?? ''; });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dok Board', href: dashboard() },
    { title: 'Commandes', href: '/commandes' },
];

const statuts = [
    { key: 'nouvelle', label: 'Nouvelles', color: 'bg-blue-500' },
    { key: 'en_attente', label: 'En attente', color: 'bg-amber-500' },
    { key: 'annulee', label: 'Annulée', color: 'bg-red-500' },
    { key: 'validee', label: 'Validée', color: 'bg-emerald-500' },
    { key: 'livree', label: 'Livrée', color: 'bg-emerald-600' },
];

function filtrer(key: string, value: string) {
    router.get('/commandes', { ...props.filters, [key]: value || undefined }, { preserveState: true });
}

function getStatusLabel(s: string) {
    return statuts.find((st) => st.key === s)?.label ?? s;
}
</script>

<template>
    <Head title="Gestion des commandes - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Gestion commandes</h1>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="s in statuts"
                    :key="s.key"
                    :variant="filters.status === s.key ? 'default' : 'outline'"
                    size="sm"
                    @click="filtrer('status', filters.status === s.key ? '' : s.key)"
                >
                    {{ s.label }} ({{ stats[s.key] ?? 0 }})
                </Button>
            </div>

            <form
                class="flex flex-wrap gap-4"
                @submit.prevent="filtrer('search', searchQuery)"
            >
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Recherche commandes, médicaments, téléphone, nom..."
                        class="pl-9"
                    />
                </div>
                <select
                    :value="filters.periode"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    @change="(e: Event) => filtrer('periode', (e.target as HTMLSelectElement).value)"
                >
                    <option value="">Toutes périodes</option>
                    <option value="aujourdhui">Aujourd'hui</option>
                    <option value="semaine">Cette semaine</option>
                    <option value="mois">Ce mois</option>
                </select>
                <Button type="submit">Rechercher</Button>
            </form>

            <div class="rounded-xl border overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">ID Cmd</th>
                            <th class="px-4 py-3 text-left font-medium">Client</th>
                            <th class="px-4 py-3 text-left font-medium">Tel</th>
                            <th class="px-4 py-3 text-left font-medium">Date</th>
                            <th class="px-4 py-3 text-left font-medium">Médicament</th>
                            <th class="px-4 py-3 text-left font-medium">Montant</th>
                            <th class="px-4 py-3 text-left font-medium">Statut</th>
                            <th class="px-4 py-3 text-left font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="cmd in commandes.data"
                            :key="cmd.id"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/commandes/${cmd.id}`"
                                    class="font-medium text-primary hover:underline"
                                >
                                    {{ cmd.numero }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">{{ cmd.client?.prenom }} {{ cmd.client?.nom }}</td>
                            <td class="px-4 py-3">{{ cmd.client?.tel }}</td>
                            <td class="px-4 py-3">{{ cmd.date }}</td>
                            <td class="px-4 py-3">
                                {{ cmd.produits?.map((p) => p.designation).join(', ') || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ Number(cmd.prix_total).toLocaleString('fr-FR') }} XAF
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="{
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900/30': cmd.status === 'nouvelle',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/30': cmd.status === 'en_attente',
                                        'bg-red-100 text-red-800 dark:bg-red-900/30': cmd.status === 'annulee',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30': ['validee', 'livree'].includes(cmd.status),
                                    }"
                                >
                                    {{ getStatusLabel(cmd.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/commandes/${cmd.id}`">Voir détails</Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="commandes.links?.length" class="flex justify-center gap-2">
                <template v-for="(link, i) in commandes.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                    >
                        {{ link.label }}
                    </Link>
                    <span v-else class="px-3 py-1 text-muted-foreground">{{ link.label }}</span>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
