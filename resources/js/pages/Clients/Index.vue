<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, Users, Phone } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

type Client = {
    id: number;
    nom: string;
    prenom: string;
    tel: string;
    adresse: string;
    zone?: string;
    nb_commandes: number;
    total_depense: number;
    panier_moyen: number;
    medicaments_frequents: string[];
    habitué: boolean;
};

const props = defineProps<{
    clients: Client[];
    zones: Array<{ id: number; designation: string }>;
    filters: { search?: string; zone_id?: string; tri?: string; frequence?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Clients', href: '/clients' },
];

const searchQuery = ref(props.filters.search ?? '');
const activeTab = ref<'liste' | 'doublons' | 'statistiques'>('liste');

watch(() => props.filters.search, (v) => { searchQuery.value = v ?? ''; });

function filtrer(key: string, value: string) {
    router.get('/clients', { ...props.filters, [key]: value || undefined }, { preserveState: true });
}

function nomComplet(c: Client) {
    return `${c.prenom} ${c.nom}`.trim();
}
</script>

<template>
    <Head title="Liste des clients - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6"
            style="background: linear-gradient(to right, rgba(91, 176, 52, 0.14) 0%, rgba(52, 176, 199, 0.14) 100%);"
        >
            <!-- Tabs -->
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex gap-2">
                        <button
                            class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="activeTab === 'liste' ? 'bg-[#459cd1] text-white' : 'bg-white/80 text-muted-foreground hover:bg-white'"
                            @click="activeTab = 'liste'"
                        >
                            Liste des clients
                        </button>
                        <Link
                            href="/clients/doublons"
                            class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="'bg-white/80 text-muted-foreground hover:bg-white'"
                        >
                            Gestion des doublons Clients
                        </Link>
                        <button
                            class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="activeTab === 'statistiques' ? 'bg-[#459cd1] text-white' : 'bg-white/80 text-muted-foreground hover:bg-white'"
                            @click="activeTab = 'statistiques'"
                        >
                            Statistiques
                        </button>
                </div>
            </div>

            <div v-if="activeTab === 'liste'" class="space-y-4">
                <form class="flex flex-wrap items-center gap-4" @submit.prevent="filtrer('search', searchQuery)">
                    <div class="relative flex-1 min-w-[200px]">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Rechercher un client..."
                            class="pl-9"
                        />
                    </div>
                    <select
                        :value="filters.tri"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="(e: Event) => filtrer('tri', (e.target as HTMLSelectElement).value)"
                    >
                        <option value="">Trier par</option>
                        <option value="nom">Nom</option>
                        <option value="commandes">Commandes</option>
                        <option value="depense">Dépenses</option>
                        <option value="recent">Récent</option>
                    </select>
                    <select
                        :value="filters.zone_id"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="(e: Event) => filtrer('zone_id', (e.target as HTMLSelectElement).value)"
                    >
                        <option value="">Toutes les zones</option>
                        <option v-for="z in zones" :key="z.id" :value="String(z.id)">{{ z.designation }}</option>
                    </select>
                    <select
                        :value="filters.frequence"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="(e: Event) => filtrer('frequence', (e.target as HTMLSelectElement).value)"
                    >
                        <option value="">Toutes les fréquences</option>
                        <option value="habitué">Habitué</option>
                        <option value="occasionnel">Occasionnel</option>
                    </select>
                    <div class="ml-auto flex items-center gap-2 rounded-lg bg-emerald-500 px-3 py-1.5 text-white">
                        <Users class="size-4" />
                        <span class="font-semibold">{{ clients.length }}</span>
                    </div>
                </form>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="c in clients"
                        :key="c.id"
                        class="rounded-xl border border-white/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95"
                    >
                        <div class="mb-3 flex items-start justify-between">
                            <div>
                                <h3 class="font-semibold text-foreground">{{ nomComplet(c) }}</h3>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <span
                                        v-if="c.zone"
                                        class="rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-800 dark:bg-sky-900/30"
                                    >
                                        {{ c.zone }}
                                    </span>
                                    <span
                                        v-if="c.habitué"
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-900/30"
                                    >
                                        Habitué
                                    </span>
                                </div>
                            </div>
                            <Button variant="secondary" size="sm" as-child>
                                <Link :href="`/clients/${c.id}`">Détails</Link>
                            </Button>
                        </div>

                        <p class="mb-3 flex items-center gap-2 text-sm text-muted-foreground">
                            <Phone class="size-4 shrink-0" />
                            Téléphone {{ c.tel }}
                        </p>

                        <div class="mb-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            <span class="flex items-baseline gap-2">
                                <span class="text-muted-foreground">Commandes</span>
                                <span>{{ c.nb_commandes }}</span>
                            </span>
                            <span class="flex items-baseline gap-2">
                                <span class="text-muted-foreground">Total Dépensé</span>
                                <strong class="text-emerald-600">{{ Number(c.total_depense).toLocaleString('fr-FR') }}&nbsp;xaf</strong>
                            </span>
                            <span class="flex items-baseline gap-2">
                                <span class="text-muted-foreground">Panier Moyen</span>
                                <strong class="text-emerald-600">{{ Number(c.panier_moyen).toLocaleString('fr-FR') }}&nbsp;xaf</strong>
                            </span>
                        </div>

                        <div v-if="c.medicaments_frequents?.length" class="space-y-1">
                            <p class="text-xs text-muted-foreground">Médicaments fréquents:</p>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="med in c.medicaments_frequents"
                                    :key="med"
                                    class="rounded bg-slate-100 px-2 py-0.5 text-xs dark:bg-slate-800"
                                >
                                    {{ med }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'doublons'" class="rounded-xl border bg-white p-8 text-center dark:border-white/10 dark:bg-white/95">
                <p class="mb-4 text-muted-foreground">La gestion des doublons s'effectue sur une page dédiée.</p>
                <Button as-child>
                    <Link href="/clients/doublons">Ouvrir la gestion des doublons</Link>
                </Button>
            </div>

            <div v-else class="rounded-xl border bg-white p-8 text-center dark:border-white/10 dark:bg-white/95">
                <p class="text-muted-foreground">Statistiques – à venir</p>
            </div>
        </div>
    </AppLayout>
</template>
