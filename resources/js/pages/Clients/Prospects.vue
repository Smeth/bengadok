<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search, UserCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    { title: 'Prospects', href: '/clients/prospects' },
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
    <Head title="Prospects clients - BengaDok" />

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

            <div
                class="flex flex-col gap-2 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-11 items-center justify-center rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                    >
                        <UserCircle class="size-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">
                            Prospects
                        </h1>
                        <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Contacts encore au stade prospect : aucune promotion
                            en « client » confirmée tant qu’aucune commande du
                            contact n’a été passée au statut
                            <span class="font-medium text-foreground"
                                >Validée</span
                            >
                            ou
                            <span class="font-medium text-foreground"
                                >Retirée (livraison terminée)</span
                            >
                            côté back-office ; la fiche rejoint alors la liste des
                            clients.
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        href="/clients"
                        class="rounded-lg px-4 py-2 text-sm font-medium bg-white/80 text-muted-foreground transition-colors hover:bg-white"
                    >
                        Liste des clients
                    </Link>
                    <Link
                        href="/clients/doublons"
                        class="rounded-lg px-4 py-2 text-sm font-medium bg-white/80 text-muted-foreground transition-colors hover:bg-white"
                    >
                        Gestion des doublons
                    </Link>
                </div>
            </div>

            <form
                class="flex flex-wrap gap-3"
                @submit.prevent="filtrerSearch"
            >
                <div class="relative min-w-[240px] flex-1">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Rechercher (nom, tél., adresse, arrdt.)..."
                        class="h-10 pl-9"
                    />
                </div>
                <Button type="submit" class="bg-[#459cd1] text-white hover:bg-[#3a87b8]">
                    Rechercher
                </Button>
            </form>

            <div class="overflow-x-auto rounded-xl border border-border bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.96]">
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
                                Cmd actives*
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
                                    <span class="text-muted-foreground/80">2ᵉ&nbsp;:</span>
                                    {{ p.tel_secondaire }}
                                </div>
                            </td>
                            <td class="max-w-[240px] px-4 py-3 text-muted-foreground">
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
            <p class="text-xs text-muted-foreground">
                *Nombre de commandes encore liées au contact (historique inclus).
                Promotion en « client » confirmée dès passage à Validée ou Retirée
                (livraison terminée) sur au moins une commande.
            </p>
        </div>
    </AppLayout>
</template>
