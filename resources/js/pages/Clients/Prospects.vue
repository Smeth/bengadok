<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Search,
    UserCircle,
    Users,
    ShoppingBag,
    UserPlus,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ClientsSectionNav from '@/components/clients/ClientsSectionNav.vue';
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
    zone?: string | null;
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
    zones: Array<{ id: number; designation: string }>;
    arrondissements: string[];
    stats: {
        total: number;
        avec_commandes: number;
        sans_commande: number;
        eligibles_promotion: number;
    };
    filters: {
        search?: string;
        zone_id?: string;
        arrondissement?: string;
        tri?: string;
    };
}>();

const page = usePage();
const flashStatus = computed(
    () =>
        (page.props.flash as { status?: string; error?: string })?.status ||
        (page.props.flash as { error?: string })?.error,
);

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
        <div
            class="relative flex min-h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6 md:p-8"
        >
            <p
                v-if="flashStatus"
                class="rounded-lg border px-4 py-2 text-sm"
                :class="
                    (page.props.flash as { error?: string })?.error
                        ? 'border-red-200 bg-red-50 text-red-800'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-800'
                "
            >
                {{ flashStatus }}
            </p>

            <ClientsSectionNav active="prospects" />

            <p class="max-w-3xl text-sm text-muted-foreground">
                Contacts créés via les commandes qui ne sont pas encore
                promus «&nbsp;client&nbsp;». La promotion est automatique
                dès qu’une commande est validée ou livrée ; vous pouvez aussi
                promouvoir manuellement.
            </p>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    class="rounded-xl border border-border bg-white p-4 shadow-sm dark:bg-white/[0.96]"
                >
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <UserCircle class="size-4" />
                        <span class="text-xs font-medium uppercase tracking-wide"
                            >Total prospects</span
                        >
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums">
                        {{ stats.total }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-border bg-white p-4 shadow-sm dark:bg-white/[0.96]"
                >
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <ShoppingBag class="size-4" />
                        <span class="text-xs font-medium uppercase tracking-wide"
                            >Avec commandes</span
                        >
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums">
                        {{ stats.avec_commandes }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-border bg-white p-4 shadow-sm dark:bg-white/[0.96]"
                >
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <Sparkles class="size-4" />
                        <span class="text-xs font-medium uppercase tracking-wide"
                            >Prêts à promouvoir</span
                        >
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-emerald-700">
                        {{ stats.eligibles_promotion }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-border bg-white p-4 shadow-sm dark:bg-white/[0.96]"
                >
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <Users class="size-4" />
                        <span class="text-xs font-medium uppercase tracking-wide"
                            >Sans commande</span
                        >
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums">
                        {{ stats.sans_commande }}
                    </p>
                </div>
            </div>

            <form
                class="flex flex-wrap items-center gap-4"
                @submit.prevent="submitSearch"
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
                <select
                    :value="filters.tri || 'recent'"
                    class="flex h-10 rounded-lg border border-white/80 bg-white px-3 py-1 text-sm text-slate-900 shadow-sm"
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
                    :value="filters.zone_id || ''"
                    class="flex h-10 rounded-lg border border-white/80 bg-white px-3 py-1 text-sm text-slate-900 shadow-sm"
                    @change="
                        (e: Event) =>
                            filtrer(
                                'zone_id',
                                (e.target as HTMLSelectElement).value,
                            )
                    "
                >
                    <option value="">Toutes les zones</option>
                    <option
                        v-for="z in zones"
                        :key="z.id"
                        :value="String(z.id)"
                    >
                        {{ z.designation }}
                    </option>
                </select>
                <select
                    :value="filters.arrondissement || ''"
                    class="flex h-10 max-w-[180px] rounded-lg border border-white/80 bg-white px-3 py-1 text-sm text-slate-900 shadow-sm"
                    @change="
                        (e: Event) =>
                            filtrer(
                                'arrondissement',
                                (e.target as HTMLSelectElement).value,
                            )
                    "
                >
                    <option value="">Tous arrondissements</option>
                    <option v-for="a in arrondissements" :key="a" :value="a">
                        {{ a }}
                    </option>
                </select>
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
                    <span class="font-semibold">{{ prospects.total }}</span>
                </div>
            </form>

            <div
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
                                Zone
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
                                {{ p.zone || '—' }}
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
                                        v-if="canPromouvoir"
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

                <p
                    v-if="!prospects.data.length"
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    Aucun prospect avec les filtres actuels.
                </p>
            </div>

            <div
                v-if="prospects.links.length > 3"
                class="flex items-center justify-between px-2"
            >
                <div class="hidden text-sm text-muted-foreground sm:block">
                    Affichage de
                    <span class="font-medium text-foreground">{{
                        prospects.from
                    }}</span>
                    à
                    <span class="font-medium text-foreground">{{
                        prospects.to
                    }}</span>
                    sur
                    <span class="font-medium text-foreground">{{
                        prospects.total
                    }}</span>
                    résultats
                </div>
                <div class="flex flex-wrap items-center gap-1">
                    <template
                        v-for="(link, pIndex) in prospects.links"
                        :key="pIndex"
                    >
                        <div
                            v-if="link.url === null"
                            class="flex min-w-9 items-center justify-center rounded-lg border border-transparent px-3 py-1.5 text-sm text-muted-foreground"
                            v-html="link.label"
                        />
                        <Link
                            v-else
                            :href="link.url"
                            class="flex min-w-9 items-center justify-center rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors hover:bg-muted/50"
                            :class="
                                link.active
                                    ? 'border-[#459cd1] bg-[#459cd1] text-white'
                                    : 'border-input bg-white text-foreground'
                            "
                        >
                            <span v-html="link.label" />
                        </Link>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
