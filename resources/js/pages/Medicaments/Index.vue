<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, Link2, Package, LayoutGrid, List } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

type Produit = {
    id: number;
    designation: string;
    dosage?: string;
    forme?: string;
    type: string;
    pu: number;
    created_at?: string;
    ventes: number;
    ca: number;
    prix_moyen: number;
    prix_min: number;
    prix_max: number;
    classement?: number;
    pharmacies: Array<{ id: number; designation: string; prix: number }>;
};

type PaginatedData<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
};

const props = defineProps<{
    produits: PaginatedData<Produit>;
    pharmacies: Array<{ id: number; designation: string }>;
    filters: { search?: string; type?: string; pharmacie_id?: string; tri?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Médicaments', href: '/medicaments' },
];

const searchQuery = ref(props.filters.search ?? '');
const activeTab = ref<'catalogue' | 'statistiques'>('catalogue');
const vueMode = ref<'cartes' | 'liste'>('cartes');

watch(() => props.filters.search, (v) => { searchQuery.value = v ?? ''; });

function filtrer(key: string, value: string) {
    router.get('/medicaments', { ...props.filters, [key]: value || undefined }, { preserveState: true });
}

function designationComplete(p: Produit) {
    return [p.designation, p.dosage].filter(Boolean).join(' ');
}
</script>

<template>
    <Head title="Catalogue Médicaments - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6"
            style="background: linear-gradient(to right, rgba(91, 176, 52, 0.14) 0%, rgba(52, 176, 199, 0.14) 100%);"
        >
            <!-- Badge + tabs -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center gap-1 rounded-full bg-emerald-500 text-white">
                        <Package class="size-4" />
                        <span class="text-sm font-bold">{{ produits.total }}</span>
                    </div>
                    <div class="flex gap-2">
                        <button
                            class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="activeTab === 'catalogue' ? 'bg-[#459cd1] text-white' : 'bg-white/80 text-muted-foreground hover:bg-white'"
                            @click="activeTab = 'catalogue'"
                        >
                            Catalogue Médicaments
                        </button>
                        <button
                            class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="activeTab === 'statistiques' ? 'bg-[#459cd1] text-white' : 'bg-white/80 text-muted-foreground hover:bg-white'"
                            @click="activeTab = 'statistiques'"
                        >
                            Statistiques
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'catalogue'" class="space-y-4">
                <form class="flex flex-wrap gap-4" @submit.prevent="filtrer('search', searchQuery)">
                    <div class="relative flex-1 min-w-[200px]">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Rechercher un médicament..."
                            class="pl-9"
                        />
                    </div>
                    <select
                        :value="filters.tri"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="(e: Event) => filtrer('tri', (e.target as HTMLSelectElement).value)"
                    >
                        <option value="">Trier par</option>
                        <option value="designation">Nom</option>
                        <option value="prix">Prix</option>
                        <option value="ventes">Ventes</option>
                    </select>
                    <select
                        :value="filters.type"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="(e: Event) => filtrer('type', (e.target as HTMLSelectElement).value)"
                    >
                        <option value="">Prescription</option>
                        <option value="Vente libre">Vente libre</option>
                        <option value="Sur ordonnance">Sur ordonnance</option>
                    </select>
                    <select
                        :value="filters.pharmacie_id"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="(e: Event) => filtrer('pharmacie_id', (e.target as HTMLSelectElement).value)"
                    >
                        <option value="">Toutes les pharmacies</option>
                        <option v-for="ph in pharmacies" :key="ph.id" :value="String(ph.id)">{{ ph.designation }}</option>
                    </select>
                    <Button type="submit">Rechercher</Button>
                    <div class="flex gap-1 rounded-lg border border-input bg-muted/30 p-1">
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors"
                            :class="vueMode === 'cartes' ? 'bg-white shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                            @click="vueMode = 'cartes'"
                        >
                            <LayoutGrid class="size-4" />
                            Cartes
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors"
                            :class="vueMode === 'liste' ? 'bg-white shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                            @click="vueMode = 'liste'"
                        >
                            <List class="size-4" />
                            Liste
                        </button>
                    </div>
                </form>

                <!-- Vue cartes -->
                <div v-if="vueMode === 'cartes'" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="p in produits.data"
                        :key="p.id"
                        class="rounded-xl border border-white/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/95"
                    >
                        <div class="mb-3 flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <Link2 class="size-4 shrink-0 text-muted-foreground" />
                                <div>
                                    <h3 class="font-semibold text-foreground">{{ designationComplete(p) }}</h3>
                                    <span
                                        class="inline-block rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800 dark:bg-blue-900/30"
                                    >
                                        {{ p.type || 'Vente libre' }}
                                    </span>
                                </div>
                            </div>
                            <Button variant="secondary" size="sm" as-child>
                                <Link :href="`/medicaments/${p.id}`">Détails</Link>
                            </Button>
                        </div>

                        <div class="space-y-2 text-sm">
                            <p class="flex flex-wrap gap-1"><span class="text-muted-foreground">Forme:</span><span class="ml-1 font-medium">{{ p.forme || '-' }}</span></p>
                            <p class="flex flex-wrap gap-1"><span class="text-muted-foreground">Prix moyen:</span><strong class="ml-1 text-emerald-600">{{ Number(p.prix_moyen).toLocaleString('fr-FR') }} xaf</strong></p>
                            <p class="flex flex-wrap gap-1"><span class="text-muted-foreground">Fourchette:</span><strong class="ml-1 text-emerald-600">{{ Number(p.prix_min).toLocaleString('fr-FR') }} - {{ Number(p.prix_max).toLocaleString('fr-FR') }} xaf</strong></p>
                            <p class="flex flex-wrap gap-1"><span class="text-muted-foreground">Vente:</span><strong class="ml-1 text-emerald-600">{{ p.ventes }} Unités</strong></p>
                            <p class="flex flex-wrap gap-1"><span class="text-muted-foreground">CA généré:</span><strong class="ml-1 text-emerald-600">{{ Number(p.ca).toLocaleString('fr-FR') }} xaf</strong></p>
                        </div>

                        <div class="mt-3">
                            <p class="mb-2 text-xs text-muted-foreground">Dernière disponibilité connue:</p>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="ph in p.pharmacies"
                                    :key="ph.id"
                                    class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs dark:bg-slate-800"
                                >
                                    {{ ph.designation }}: {{ Number(ph.prix).toLocaleString('fr-FR') }} xaf
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vue liste -->
                <div v-else class="overflow-hidden rounded-xl border border-white/80 bg-white shadow-sm dark:border-white/10 dark:bg-white/95">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50">
                                    <th class="px-4 py-3 text-left font-medium">Médicament</th>
                                    <th class="px-4 py-3 text-left font-medium">Type</th>
                                    <th class="px-4 py-3 text-left font-medium">Forme</th>
                                    <th class="px-4 py-3 text-right font-medium">Prix moyen</th>
                                    <th class="px-4 py-3 text-right font-medium">Fourchette</th>
                                    <th class="px-4 py-3 text-right font-medium">Ventes</th>
                                    <th class="px-4 py-3 text-right font-medium">CA généré</th>
                                    <th class="px-4 py-3 text-right font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="p in produits.data"
                                    :key="p.id"
                                    class="border-b last:border-0 hover:bg-muted/30"
                                >
                                    <td class="px-4 py-3 font-medium">{{ designationComplete(p) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800 dark:bg-blue-900/30">
                                            {{ p.type || 'Vente libre' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ p.forme || '-' }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-emerald-600">{{ Number(p.prix_moyen).toLocaleString('fr-FR') }} xaf</td>
                                    <td class="px-4 py-3 text-right text-muted-foreground">{{ Number(p.prix_min).toLocaleString('fr-FR') }} - {{ Number(p.prix_max).toLocaleString('fr-FR') }} xaf</td>
                                    <td class="px-4 py-3 text-right">{{ p.ventes }} Unités</td>
                                    <td class="px-4 py-3 text-right font-medium text-emerald-600">{{ Number(p.ca).toLocaleString('fr-FR') }} xaf</td>
                                    <td class="px-4 py-3 text-right">
                                        <Button variant="secondary" size="sm" as-child>
                                            <Link :href="`/medicaments/${p.id}`">Détails</Link>
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="produits.links.length > 3" class="mt-4 flex items-center justify-between px-2">
                    <div class="hidden text-sm text-muted-foreground sm:block">
                        Affichage de <span class="font-medium text-foreground">{{ produits.from }}</span> à
                        <span class="font-medium text-foreground">{{ produits.to }}</span> sur
                        <span class="font-medium text-foreground">{{ produits.total }}</span> résultats
                    </div>
                    <div class="flex flex-wrap items-center gap-1">
                        <template v-for="(link, pIndex) in produits.links" :key="pIndex">
                            <div
                                v-if="link.url === null"
                                class="flex min-w-9 items-center justify-center rounded-lg border border-transparent px-3 py-1.5 text-sm text-muted-foreground"
                                v-html="link.label"
                            />
                            <Link
                                v-else
                                :href="link.url"
                                class="flex min-w-9 items-center justify-center rounded-lg border px-3 py-1.5 text-sm transition-colors hover:bg-muted/50 font-medium"
                                :class="link.active ? 'bg-[#459cd1] text-white border-[#459cd1]' : 'border-input bg-white text-foreground'"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-xl border bg-white p-8 text-center dark:border-white/10 dark:bg-white/95">
                <p class="text-muted-foreground">Statistiques – à venir</p>
            </div>
        </div>
    </AppLayout>
</template>
