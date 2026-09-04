<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    UserCircle,
    Users,
    ShoppingBag,
    UserPlus,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ModuleEmptyState from '@/components/shared/ModuleEmptyState.vue';
import ModuleFilterPanel from '@/components/shared/ModuleFilterPanel.vue';
import ModulePagination from '@/components/shared/ModulePagination.vue';
import ModuleStatCard from '@/components/shared/ModuleStatCard.vue';
import ClientsSectionNav from '@/components/clients/ClientsSectionNav.vue';
import { modulePageClass, modulePaginationWrapperClass, moduleSelectClass } from '@/lib/bengadokUi';
import { Button } from '@/components/ui/button';
import FlashToastHost from '@/components/FlashToastHost.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { clientNomComplet } from '@/lib/clientDisplayName';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type ProspectStatut = 'sans_commande' | 'en_cours' | 'eligible_promotion';

type ProspectRow = {
    id: number;
    nom: string | null;
    prenom: string | null;
    tel: string;
    tel_secondaire?: string | null;
    adresse: string;
    arrondissement?: string | null;
    nb_commandes: number;
    nb_commandes_reussies: number;
    derniere_commande?: string | null;
    updated_at?: string | null;
    statut: ProspectStatut;
};

type PaginatedProspects = {
    data: ProspectRow[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
};

const props = defineProps<{
    prospects: PaginatedProspects;
    arrondissements: string[];
    stats: {
        total: number;
        avec_commandes: number;
        sans_commande: number;
        eligibles_promotion: number;
    };
    filters: {
        search?: string;
        arrondissement?: string;
        tri?: string;
    };
}>();

const page = usePage();

const canPromouvoir = computed(() => {
    const roles =
        (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ??
        [];
    return ['admin', 'super_admin', 'agent_call_center'].some((r) =>
        roles.includes(r),
    );
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Clients', href: '/clients' },
    { title: 'Prospects', href: '/clients/prospects' },
];

const searchQuery = ref(props.filters.search ?? '');
const promotingId = ref<number | null>(null);

watch(
    () => props.filters.search,
    (v) => {
        searchQuery.value = v ?? '';
    },
);

function filtrer(key: string, value: string) {
    router.get(
        '/clients/prospects',
        {
            ...props.filters,
            [key]: value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function submitSearch() {
    filtrer('search', searchQuery.value.trim());
}

function statutLabel(statut: ProspectStatut): string {
    const map: Record<ProspectStatut, string> = {
        sans_commande: 'Sans commande',
        en_cours: 'Commande en cours',
        eligible_promotion: 'Prêt à promouvoir',
    };
    return map[statut];
}

function statutClass(statut: ProspectStatut): string {
    const map: Record<ProspectStatut, string> = {
        sans_commande: 'bg-slate-100 text-slate-700',
        en_cours: 'bg-sky-100 text-sky-800',
        eligible_promotion: 'bg-emerald-100 text-emerald-800',
    };
    return map[statut];
}

function promouvoir(prospect: ProspectRow) {
    if (
        !canPromouvoir.value ||
        !confirm(
            `Promouvoir ${clientNomComplet(prospect)} en client définitif ?`,
        )
    ) {
        return;
    }

    promotingId.value = prospect.id;
    router.patch(
        `/clients/${prospect.id}/promouvoir-client`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                promotingId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Prospects - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="modulePageClass">
            <ClientsSectionNav active="prospects" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <ModuleStatCard
                    label="Total prospects"
                    :value="stats.total"
                    :icon="UserCircle"
                />
                <ModuleStatCard
                    label="Avec commandes"
                    :value="stats.avec_commandes"
                    :icon="ShoppingBag"
                />
                <ModuleStatCard
                    label="Prêts à promouvoir"
                    :value="stats.eligibles_promotion"
                    :icon="Sparkles"
                    value-class="text-emerald-700"
                />
                <ModuleStatCard
                    label="Sans commande"
                    :value="stats.sans_commande"
                    :icon="Users"
                />
            </div>

            <ModuleFilterPanel
                v-model:search="searchQuery"
                placeholder="Rechercher (nom, tél., adresse, arrdt.)..."
                show-submit
                :counter="prospects.total"
                :counter-icon="UserCircle"
                counter-class="bg-amber-500"
                @submit="submitSearch"
            >
                <select
                    :value="filters.tri || 'recent'"
                    :class="moduleSelectClass"
                    @change="
                        (e: Event) =>
                            filtrer(
                                'tri',
                                (e.target as HTMLSelectElement).value,
                            )
                    "
                >
                    <option value="recent">Plus récents</option>
                    <option value="nom">Nom</option>
                    <option value="commandes">Nb commandes</option>
                </select>
                <select
                    :value="filters.arrondissement || ''"
                    :class="[moduleSelectClass, 'max-w-[200px]']"
                    @change="
                        (e: Event) =>
                            filtrer(
                                'arrondissement',
                                (e.target as HTMLSelectElement).value,
                            )
                    "
                >
                    <option value="">Tous les arrondissements</option>
                    <option v-for="a in arrondissements" :key="a" :value="a">
                        {{ a }}
                    </option>
                </select>
            </ModuleFilterPanel>

            <div
                v-if="prospects.data.length"
                class="overflow-x-auto rounded-xl border border-border bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.96]"
            >
                <table class="w-full min-w-[980px] text-sm">
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
                                Arrondissement
                            </th>
                            <th class="px-4 py-3 text-center font-medium">
                                Commandes
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Dernière cmd.
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Statut
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in prospects.data"
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
                                class="max-w-[220px] px-4 py-3 text-muted-foreground"
                            >
                                <span class="line-clamp-2" :title="p.adresse">
                                    {{ p.adresse || '—' }}
                                </span>
                                <span
                                    v-if="p.arrondissement"
                                    class="mt-0.5 block text-xs text-muted-foreground/80"
                                >
                                    {{ p.arrondissement }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ p.arrondissement || '—' }}
                            </td>
                            <td
                                class="px-4 py-3 text-center tabular-nums font-medium"
                            >
                                {{ p.nb_commandes }}
                                <span
                                    v-if="p.nb_commandes_reussies > 0"
                                    class="block text-xs font-normal text-emerald-700"
                                >
                                    {{ p.nb_commandes_reussies }} validée(s)
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ p.derniere_commande || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="statutClass(p.statut)"
                                >
                                    {{ statutLabel(p.statut) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex flex-wrap items-center justify-end gap-2"
                                >
                                    <Link
                                        :href="`/clients/${p.id}`"
                                        class="text-[13px] font-semibold text-[#459cd1] hover:underline"
                                    >
                                        Fiche
                                    </Link>
                                    <Button
                                        v-if="
                                            canPromouvoir &&
                                            p.statut === 'eligible_promotion'
                                        "
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="h-8 gap-1 border-emerald-300 text-emerald-800 hover:bg-emerald-50"
                                        :disabled="promotingId === p.id"
                                        @click="promouvoir(p)"
                                    >
                                        <UserPlus class="size-3.5" />
                                        Promouvoir
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ModuleEmptyState
                v-else
                message="Aucun prospect avec les filtres actuels."
            />

            <div :class="modulePaginationWrapperClass">
                <ModulePagination
                    :links="prospects.links"
                    :from="prospects.from"
                    :to="prospects.to"
                    :total="prospects.total"
                />
            </div>
        </div>

        <FlashToastHost />
    </AppLayout>
</template>
