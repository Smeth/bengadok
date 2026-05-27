<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search, UserCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ClientsSectionNav from '@/components/clients/ClientsSectionNav.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { clientNomComplet } from '@/lib/clientDisplayName';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type ProspectRow = {
    id: number;
    nom: string | null;
    prenom: string | null;
    tel: string;
    tel_secondaire?: string | null;
    adresse: string;
    arrondissement?: string | null;
    nb_commandes: number;
    updated_at?: string | null;
};

const props = defineProps<{
    prospects: ProspectRow[];
    filters: { search?: string };
}>();

const page = usePage();
const flashStatus = computed(
    () =>
        (page.props.flash as { status?: string; success?: string })?.status ||
        (page.props.flash as { success?: string })?.success,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Clients', href: '/clients' },
];

const searchQuery = ref(props.filters.search ?? '');

watch(
    () => props.filters.search,
    (v) => {
        searchQuery.value = v ?? '';
    },
);

function filtrerSearch() {
    router.get(
        '/clients/prospects',
        { search: searchQuery.value.trim() || undefined },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="Prospects - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="relative flex min-h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6 md:p-8"
        >
            <p
                v-if="flashStatus"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"
            >
                {{ flashStatus }}
            </p>

            <ClientsSectionNav active="prospects" />

            <form
                class="flex flex-wrap items-center gap-4"
                @submit.prevent="filtrerSearch"
            >
                <div class="relative min-w-[200px] flex-1">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-500"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Rechercher (nom, tél., adresse, arrdt.)..."
                        class="h-10 rounded-full border-0 bg-white pl-10 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-500 focus-visible:ring-2 focus-visible:ring-white/90"
                    />
                </div>
                <Button
                    type="submit"
                    class="rounded-lg bg-[#459cd1] text-white hover:bg-[#3a87b8]"
                >
                    Rechercher
                </Button>
                <div
                    class="ml-auto flex items-center gap-2 rounded-lg bg-amber-500 px-3 py-1.5 text-white"
                >
                    <UserCircle class="size-4" />
                    <span class="font-semibold">{{ prospects.length }}</span>
                </div>
            </form>

            <div
                class="overflow-x-auto rounded-xl border border-border bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.96]"
            >
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                Nom
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Téléphones
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Adresse
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Arrdt.
                            </th>
                            <th class="px-4 py-3 text-center font-medium">
                                Commandes
                            </th>
                            <th class="px-4 py-3 text-right font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in prospects"
                            :key="p.id"
                            class="border-b border-border/60 transition-colors hover:bg-muted/40"
                        >
                            <td class="px-4 py-3 font-semibold">
                                {{ clientNomComplet(p) }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div>{{ p.tel }}</div>
                                <div v-if="p.tel_secondaire" class="text-xs">
                                    <span class="text-muted-foreground/80"
                                        >2ᵉ&nbsp;:</span
                                    >
                                    {{ p.tel_secondaire }}
                                </div>
                            </td>
                            <td
                                class="max-w-[240px] px-4 py-3 text-muted-foreground"
                            >
                                <span class="line-clamp-2" :title="p.adresse">
                                    {{ p.adresse || '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ p.arrondissement || '—' }}
                            </td>
                            <td
                                class="px-4 py-3 text-center tabular-nums font-medium text-foreground"
                            >
                                {{ p.nb_commandes }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/clients/${p.id}`"
                                    class="text-[13px] font-semibold text-[#459cd1] hover:underline"
                                >
                                    Fiche
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-if="!prospects.length"
                class="rounded-xl border border-dashed py-10 text-center text-sm text-muted-foreground"
            >
                Aucun prospect avec les filtres actuels.
            </p>
        </div>
    </AppLayout>
</template>
