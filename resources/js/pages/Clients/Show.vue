<script setup lang="ts">
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
import { computed, ref } from 'vue';
import ClientEnrichirProfilModal from '@/components/clients/ClientEnrichirProfilModal.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { clientNomComplet } from '@/lib/clientDisplayName';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

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
        frequence_label?: string | null;
        pour_soi: number;
        pour_tiers: number;
        pct_soi: number;
        pct_tiers: number;
        categories_tiers: string[];
        tiers_frequent: string;
        medicaments_frequents: string[];
        niches?: string[];
        niches_labels?: string[];
        canal_acquisition?: string | null;
        canal_acquisition_label?: string | null;
    };
}>();

const enrichModalOpen = ref(false);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Clients', href: '/clients' },
    {
        title: clientNomComplet(props.client),
        href: `/clients/${props.client.id}`,
    },
]);

function nomComplet() {
    return clientNomComplet(props.client);
}

/** Affichage type maquette : +242 si pas déjà présent */
function telAffiche(tel: string) {
    const t = (tel ?? '').trim();
    if (!t) return '—';
    if (t.startsWith('+')) return t;
    const digits = t.replace(/\D/g, '');
    if (digits.length >= 9) return `+242 ${t}`;
    return t;
}

const tabClassInactive =
    'rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-white/80 dark:hover:bg-white/10';
const tabClassActive =
    'rounded-lg px-4 py-2 text-sm font-medium bg-[#459cd1] text-white shadow-sm';
</script>

<template>
    <Head :title="`Profil Client - ${nomComplet()} - BengaDok`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="relative flex min-h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6 md:p-8"
        >
            <!-- En-tête maquette -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-4">
                    <Button
                        variant="link"
                        class="w-fit -ml-2 h-auto p-0"
                        as-child
                    >
                        <Link
                            href="/clients"
                            class="flex items-center gap-2 text-[#459cd1] hover:underline"
                        >
                            <ArrowLeft class="size-4 shrink-0" />
                            Retour à la liste
                        </Link>
                    </Button>
                    <h1
                        class="text-2xl font-bold tracking-tight text-foreground"
                    >
                        Profil Client
                    </h1>
                </div>
                <Button
                    type="button"
                    class="rounded-lg bg-[#459cd1] px-5 text-white shadow-sm hover:bg-[#3a87b8]"
                    @click="enrichModalOpen = true"
                >
                    <Pencil class="mr-2 size-4" />
                    Enrichir le profil
                </Button>
            </div>

            <!-- Onglets (Statistiques = contexte fiche détail, comme Figma) -->
            <div
                class="flex flex-wrap gap-2 border-b border-slate-200/80 pb-3 dark:border-white/10"
            >
                <Link href="/clients" :class="tabClassInactive"
                    >Liste des clients</Link
                >
                <Link href="/clients/doublons" :class="tabClassInactive"
                    >Gestion des doublons Clients</Link
                >
                <span :class="tabClassActive">Statistiques</span>
            </div>

            <!-- Grille 1/3 + 2/3 (maquette) -->
            <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                <!-- Colonne gauche : identité & contact -->
                <div
                    class="rounded-2xl border border-white/90 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.97] lg:col-span-4"
                >
                    <div
                        class="mb-6 flex flex-col items-center text-center sm:flex-row sm:items-start sm:text-left"
                    >
                        <div
                            class="mb-3 flex size-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-100 to-sky-50 text-sky-600 sm:mb-0 sm:mr-4"
                        >
                            <User class="size-10" stroke-width="1.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-xl font-bold text-foreground">
                                {{ nomComplet() }}
                            </h2>
                            <div
                                class="mt-2 flex flex-wrap justify-center gap-2 sm:justify-start"
                            >
                                <span
                                    v-if="client.frequence_label"
                                    class="inline-flex rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-semibold text-violet-800 dark:bg-violet-900/35 dark:text-violet-200"
                                >
                                    {{ client.frequence_label }}
                                </span>
                                <span
                                    v-else-if="client.habitué"
                                    class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900 dark:bg-amber-900/35 dark:text-amber-100"
                                >
                                    Habitué
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 text-sm">
                        <div class="flex gap-3">
                            <Phone
                                class="mt-0.5 size-4 shrink-0 text-[#459cd1]"
                            />
                            <div>
                                <p
                                    class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Téléphone
                                </p>
                                <p class="font-medium text-foreground">
                                    {{ telAffiche(client.tel) }}
                                </p>
                                <p
                                    v-if="client.tel_secondaire"
                                    class="mt-1 text-muted-foreground"
                                >
                                    Secondaire :
                                    {{ telAffiche(client.tel_secondaire) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <MapPin
                                class="mt-0.5 size-4 shrink-0 text-[#459cd1]"
                            />
                            <div>
                                <p
                                    class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Adresse
                                </p>
                                <p class="text-foreground">
                                    {{ client.adresse || '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <MapPin
                                class="mt-0.5 size-4 shrink-0 text-[#459cd1]"
                            />
                            <div>
                                <p
                                    class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Zone / Arrondissement
                                </p>
                                <p class="font-medium text-[#459cd1]">
                                    {{
                                        client.zone
                                            ? `${client.zone}, Brazzaville`
                                            : '—'
                                    }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="grid gap-3 border-t border-slate-100 pt-4 dark:border-white/10 sm:grid-cols-2"
                        >
                            <div class="flex gap-2">
                                <Calendar
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        Client depuis
                                    </p>
                                    <p class="font-semibold text-foreground">
                                        {{ client.client_depuis || '—' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Calendar
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        Dernière commande
                                    </p>
                                    <p class="font-semibold text-foreground">
                                        {{ client.derniere_commande || '—' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="border-t border-slate-100 pt-4 dark:border-white/10"
                        >
                            <p
                                class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                <Users class="size-3.5" />
                                Niche(s)
                            </p>
                            <div
                                v-if="client.niches_labels?.length"
                                class="flex flex-wrap gap-2"
                            >
                                <span
                                    v-for="n in client.niches_labels"
                                    :key="n"
                                    class="rounded-full bg-sky-50 px-3 py-1 text-xs font-medium text-sky-900 ring-1 ring-sky-100 dark:bg-sky-950/40 dark:text-sky-100 dark:ring-sky-900/50"
                                >
                                    {{ n }}
                                </span>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                —
                            </p>
                        </div>
                        <div>
                            <p
                                class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                <TrendingUp class="size-3.5" />
                                Canal d'acquisition
                            </p>
                            <span
                                v-if="client.canal_acquisition_label"
                                class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-900 ring-1 ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-100"
                            >
                                {{ client.canal_acquisition_label }}
                            </span>
                            <p v-else class="text-sm text-muted-foreground">
                                —
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Colonne droite : KPI + habitudes -->
                <div class="flex flex-col gap-6 lg:col-span-8">
                    <!-- Trois cartes KPI blanches (maquette) -->
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div
                            class="rounded-2xl border border-slate-200/90 bg-white p-5 text-center shadow-sm dark:border-white/10 dark:bg-white/[0.97]"
                        >
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Commandes
                            </p>
                            <p
                                class="mt-2 text-3xl font-black tabular-nums text-foreground"
                            >
                                {{ client.nb_commandes }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200/90 bg-white p-5 text-center shadow-sm dark:border-white/10 dark:bg-white/[0.97]"
                        >
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Total dépensé
                            </p>
                            <p
                                class="mt-2 text-2xl font-black tabular-nums text-foreground"
                            >
                                {{
                                    Number(client.total_depense).toLocaleString(
                                        'fr-FR',
                                    )
                                }}
                                <span
                                    class="text-base font-bold text-emerald-600"
                                    >xaf</span
                                >
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200/90 bg-white p-5 text-center shadow-sm dark:border-white/10 dark:bg-white/[0.97]"
                        >
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Panier moyen
                            </p>
                            <p
                                class="mt-2 text-2xl font-black tabular-nums text-foreground"
                            >
                                {{
                                    Number(client.panier_moyen).toLocaleString(
                                        'fr-FR',
                                    )
                                }}
                                <span
                                    class="text-base font-bold text-emerald-600"
                                    >xaf</span
                                >
                            </p>
                        </div>
                    </div>

                    <!-- Habitudes de commande -->
                    <div
                        class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.97]"
                    >
                        <h3
                            class="mb-5 flex items-center gap-2 text-lg font-bold text-foreground"
                        >
                            <RefreshCw class="size-5 text-[#459cd1]" />
                            Habitudes de commande
                        </h3>

                        <div class="mb-6 grid gap-4 sm:grid-cols-2">
                            <div
                                class="rounded-xl border-2 border-[#459cd1]/40 bg-sky-50/30 p-5 text-center dark:bg-sky-950/20"
                            >
                                <p
                                    class="text-3xl font-black tabular-nums text-[#459cd1]"
                                >
                                    {{ client.pour_soi }}
                                </p>
                                <p
                                    class="mt-1 text-sm font-medium text-foreground"
                                >
                                    Commandes pour soi
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ client.pct_soi }}% du total
                                </p>
                            </div>
                            <div
                                class="rounded-xl border-2 border-[#459cd1]/40 bg-sky-50/30 p-5 text-center dark:bg-sky-950/20"
                            >
                                <p
                                    class="text-3xl font-black tabular-nums text-[#459cd1]"
                                >
                                    {{ client.pour_tiers }}
                                </p>
                                <p
                                    class="mt-1 text-sm font-medium text-foreground"
                                >
                                    Commandes pour tiers
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ client.pct_tiers }}% du total
                                </p>
                            </div>
                        </div>

                        <div
                            class="space-y-5 border-t border-slate-100 pt-5 dark:border-white/10"
                        >
                            <div>
                                <p
                                    class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                                >
                                    Catégories des tiers
                                </p>
                                <div
                                    v-if="client.categories_tiers?.length"
                                    class="flex flex-wrap gap-2"
                                >
                                    <span
                                        v-for="cat in client.categories_tiers"
                                        :key="cat"
                                        class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-medium text-foreground dark:bg-slate-800 dark:text-slate-100"
                                    >
                                        {{ cat }}
                                    </span>
                                </div>
                                <p v-else class="text-sm text-muted-foreground">
                                    —
                                </p>
                            </div>
                            <div>
                                <p
                                    class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                                >
                                    Tiers les plus fréquents
                                </p>
                                <span
                                    v-if="
                                        client.tiers_frequent &&
                                        client.tiers_frequent !== '-'
                                    "
                                    class="inline-flex rounded-full bg-slate-100 px-3 py-1.5 text-sm font-medium dark:bg-slate-800"
                                >
                                    {{ client.tiers_frequent }}
                                </span>
                                <p v-else class="text-sm text-muted-foreground">
                                    —
                                </p>
                            </div>
                            <div>
                                <p
                                    class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                                >
                                    Médicaments fréquents
                                </p>
                                <div
                                    v-if="client.medicaments_frequents?.length"
                                    class="flex flex-wrap gap-2"
                                >
                                    <span
                                        v-for="med in client.medicaments_frequents"
                                        :key="med"
                                        class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-medium dark:bg-slate-800"
                                    >
                                        {{ med }}
                                    </span>
                                </div>
                                <p v-else class="text-sm text-muted-foreground">
                                    —
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ClientEnrichirProfilModal
            :open="enrichModalOpen"
            :client-id="client.id"
            :initial-niches="client.niches ?? []"
            :initial-canal="client.canal_acquisition ?? null"
            @update:open="enrichModalOpen = $event"
        />
    </AppLayout>
</template>
