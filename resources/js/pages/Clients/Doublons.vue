<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Search,
    Filter,
    Check,
    Phone,
    MapPin,
    ListOrdered,
    XCircle,
    CheckCircle2,
    Merge,
    AlertCircle,
    UsersRound,
    CheckCheck,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import ConfirmModal from '@/components/ConfirmModal.vue';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import { clientNomComplet } from '@/lib/clientDisplayName';

type ClientInGroup = {
    id: number;
    nom: string | null;
    prenom: string | null;
    tel: string;
    adresse: string;
    zone?: string;
    nb_commandes: number;
    total_depense: number;
    created_at: string;
    derniere_commande: string;
    is_principal: boolean;
};

type Groupe = {
    id: number;
    numero: string;
    statut: 'en_attente' | 'verifie' | 'fusionne' | 'ignore';
    criteres: string[];
    clients: ClientInGroup[];
    total_si_fusion: { commandes: number; montant: number };
};

const props = defineProps<{
    groupes: Groupe[];
    stats: {
        en_attente: number;
        verifies: number;
        fusionnes: number;
        total_clients: number;
    };
    filters: { search?: string; statut?: string; tri?: string };
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props.flash as { success?: string } | undefined)?.success);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Clients', href: '/clients' },
    { title: 'Gestion des doublons', href: '/clients/doublons' },
];

const searchQuery = ref(props.filters.search ?? '');
const modalFusionOpen = ref(false);
const groupeEnFusion = ref<Groupe | null>(null);
const optionNumeroSecondaire = ref(true);
const showIgnorerModal = ref(false);
const groupeToIgnorer = ref<Groupe | null>(null);

watch(() => props.filters.search, (v) => { searchQuery.value = v ?? ''; });

function filtrer(key: string, value: string) {
    router.get('/clients/doublons', { ...props.filters, [key]: value || undefined }, { preserveState: true });
}

function nomComplet(c: ClientInGroup) {
    return clientNomComplet(c);
}

const principalPourModal = computed(() => {
    const g = groupeEnFusion.value;
    if (!g?.clients?.length) return null;
    return g.clients.find((c) => c.is_principal) ?? g.clients[0];
});

const dupliquesPourModal = computed(() => {
    const g = groupeEnFusion.value;
    if (!g?.clients?.length) return [];
    return g.clients.filter((c) => !c.is_principal);
});

const premierDuplique = computed(() => dupliquesPourModal.value[0] ?? null);

const commandesRattachees = computed(() => {
    return dupliquesPourModal.value.reduce((s, c) => s + c.nb_commandes, 0);
});

const totalApresFusion = computed(() => {
    const g = groupeEnFusion.value;
    if (!g) return { commandes: 0, montant: 0 };
    return {
        commandes: g.total_si_fusion.commandes,
        montant: g.total_si_fusion.montant,
    };
});

function ouvrirModalFusion(g: Groupe) {
    if (g.statut === 'fusionne') return;
    groupeEnFusion.value = g;
    optionNumeroSecondaire.value = true;
    modalFusionOpen.value = true;
}

function fermerModalFusion() {
    modalFusionOpen.value = false;
    groupeEnFusion.value = null;
}

function confirmerFusion() {
    const g = groupeEnFusion.value;
    if (!g) return;
    router.patch(`/clients/doublons/${g.id}/fusionner`, { ajouter_tel_secondaire: optionNumeroSecondaire.value }, { preserveScroll: true });
    fermerModalFusion();
}

function openIgnorerModal(g: Groupe) {
    groupeToIgnorer.value = g;
    showIgnorerModal.value = true;
}

function confirmIgnorer() {
    if (!groupeToIgnorer.value) return;
    router.patch(`/clients/doublons/${groupeToIgnorer.value.id}/ignorer`, {}, {
        preserveScroll: true,
        onSuccess: () => { showIgnorerModal.value = false; groupeToIgnorer.value = null; },
    });
}

function verifier(g: Groupe) {
    router.patch(`/clients/doublons/${g.id}/verifier`, {}, { preserveScroll: true });
}

const statutBadgeClasses: Record<string, string> = {
    en_attente: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50',
    verifie: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50',
    fusionne: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50',
    ignore: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
};

const statutLabels: Record<string, string> = {
    en_attente: 'En Attente',
    verifie: 'Vérifié',
    fusionne: 'Fusionné',
    ignore: 'Ignoré',
};
</script>

<template>
    <Head title="Gestion des doublons Clients - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6"
            style="background: linear-gradient(to right, rgba(91, 176, 52, 0.14) 0%, rgba(52, 176, 199, 0.14) 100%);"
        >
            <div v-if="flashSuccess" class="rounded-lg bg-emerald-100 py-3 px-4 text-sm font-medium text-emerald-800 dark:bg-emerald-900/50">
                {{ flashSuccess }}
            </div>

            <!-- Tabs -->
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex gap-2">
                    <Link
                        href="/clients"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-white/80"
                    >
                        Liste des clients
                    </Link>
                    <button class="rounded-lg px-4 py-2 text-sm font-medium bg-[#459cd1] text-white">
                        Gestion des doublons Clients
                    </button>
                    <Link
                        href="/clients"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-white/80"
                    >
                        Statistiques
                    </Link>
                </div>
            </div>

            <!-- Search & Filters -->
            <form class="flex flex-wrap items-center gap-4" @submit.prevent="filtrer('search', searchQuery)">
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Rechercher par nom ou tél..."
                        class="pl-9"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Filter class="size-4 text-muted-foreground" />
                    <select
                        :value="filters.tri"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="(e: Event) => filtrer('tri', (e.target as HTMLSelectElement).value)"
                    >
                        <option value="">Trier par</option>
                        <option value="recent">Récent</option>
                        <option value="commandes">Commandes</option>
                        <option value="montant">Montant</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <Check class="size-4 text-muted-foreground" />
                    <select
                        :value="filters.statut"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        @change="(e: Event) => filtrer('statut', (e.target as HTMLSelectElement).value)"
                    >
                        <option value="">Toutes les statuts</option>
                        <option value="en_attente">En Attente</option>
                        <option value="verifie">Vérifiés</option>
                        <option value="fusionne">Fusionnés</option>
                        <option value="ignore">Ignorés</option>
                    </select>
                </div>
            </form>

            <!-- Stats cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/50 dark:bg-amber-900/20">
                    <p class="text-3xl font-bold text-amber-700 dark:text-amber-400">{{ stats.en_attente }}</p>
                    <p class="text-sm text-amber-800 dark:text-amber-300">Groupes à traiter</p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800/50 dark:bg-blue-900/20">
                    <p class="text-3xl font-bold text-blue-700 dark:text-blue-400">{{ stats.verifies }}</p>
                    <p class="text-sm text-blue-800 dark:text-blue-300">Groupes vérifiés</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800/50 dark:bg-emerald-900/20">
                    <p class="text-3xl font-bold text-emerald-700 dark:text-emerald-400">{{ stats.fusionnes }}</p>
                    <p class="text-sm text-emerald-800 dark:text-emerald-300">Profils fusionnés</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-white/5">
                    <p class="text-3xl font-bold text-slate-800 dark:text-slate-200">{{ stats.total_clients }}</p>
                    <p class="text-sm text-muted-foreground">Clients concernés</p>
                </div>
            </div>

            <!-- Groupes list -->
            <div class="space-y-6">
                <div
                    v-for="g in groupes"
                    :key="g.id"
                    class="rounded-xl border border-white/80 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/95"
                >
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">
                                Groupe de doublons #{{ g.numero }}
                            </h3>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span
                                    :class="['rounded-full px-2 py-0.5 text-xs font-medium', statutBadgeClasses[g.statut] || 'bg-slate-100']"
                                >
                                    {{ statutLabels[g.statut] || g.statut }}
                                </span>
                                <span
                                    v-for="crit in g.criteres"
                                    :key="crit"
                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-700"
                                >
                                    {{ crit }}
                                </span>
                            </div>
                        </div>
                        <div v-if="g.statut !== 'fusionne'" class="rounded-lg border border-[#459cd1]/30 bg-sky-50/50 px-4 py-2 dark:bg-sky-900/20">
                            <p class="text-xs text-muted-foreground">Total si fusion</p>
                            <p class="font-semibold text-[#459cd1]">
                                {{ g.total_si_fusion.commandes }} Commandes · {{ Number(g.total_si_fusion.montant).toLocaleString('fr-FR') }} xaf
                            </p>
                        </div>
                        <div v-else class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 dark:bg-emerald-900/20">
                            <p class="text-xs text-muted-foreground">Total si fusion</p>
                            <p class="font-semibold text-emerald-700 dark:text-emerald-400">
                                {{ g.total_si_fusion.commandes }} Commandes · {{ Number(g.total_si_fusion.montant).toLocaleString('fr-FR') }} xaf
                            </p>
                        </div>
                    </div>

                    <div class="mb-4 grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="c in g.clients"
                            :key="c.id"
                            class="rounded-lg border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
                        >
                            <div class="mb-2 flex items-center gap-2">
                                <h4 class="font-semibold text-foreground">{{ nomComplet(c) }}</h4>
                                <span
                                    v-if="c.is_principal"
                                    class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-600"
                                >
                                    Principal suggéré
                                </span>
                            </div>
                            <p class="mb-1 flex items-center gap-2 text-sm text-muted-foreground">
                                <Phone class="size-4 shrink-0" />
                                {{ c.tel }}
                            </p>
                            <p class="mb-2 flex items-center gap-2 text-sm text-muted-foreground">
                                <MapPin class="size-4 shrink-0" />
                                {{ c.adresse || '-' }}{{ c.zone ? `, ${c.zone}` : '' }}
                            </p>
                            <p class="mb-1 flex items-center gap-2 text-sm">
                                <ListOrdered class="size-4 shrink-0" />
                                {{ c.nb_commandes }} commandes · {{ Number(c.total_depense).toLocaleString('fr-FR') }} F
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Créé le {{ c.created_at }} · Dernière commande {{ c.derniere_commande || '-' }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="g.statut === 'fusionne'"
                        class="rounded-lg bg-emerald-100 py-3 px-4 text-center text-sm font-medium text-emerald-800 dark:bg-emerald-900/50"
                    >
                        Profils fusionnés avec succès
                    </div>
                    <div
                        v-else-if="g.statut === 'ignore'"
                        class="rounded-lg bg-slate-200 py-3 px-4 text-center text-sm font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                    >
                        Groupe ignoré – ces clients sont considérés comme distincts
                    </div>
                    <div
                        v-else-if="g.statut === 'verifie'"
                        class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800/50 dark:bg-blue-900/20"
                    >
                        <p class="text-sm text-blue-800 dark:text-blue-300">
                            Vérifié – Aucune action nécessaire pour le moment
                        </p>
                        <Button
                            class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]"
                            @click="ouvrirModalFusion(g)"
                        >
                            <Merge class="mr-2 size-4" />
                            Fusionner quand même
                        </Button>
                    </div>
                    <div v-else class="flex flex-wrap items-center justify-end gap-2">
                        <Button variant="outline" class="border-slate-300" @click="openIgnorerModal(g)">
                            <XCircle class="mr-2 size-4" />
                            Ignorer
                        </Button>
                        <Button
                            variant="outline"
                            class="border-blue-300 text-blue-700 hover:bg-blue-50"
                            @click="verifier(g)"
                        >
                            <CheckCircle2 class="mr-2 size-4" />
                            Marquer comme vérifié
                        </Button>
                        <Button class="bg-emerald-600 text-white hover:bg-emerald-700" @click="ouvrirModalFusion(g)">
                            <Merge class="mr-2 size-4" />
                            Fusionner les profils
                        </Button>
                    </div>
                </div>

                <div
                    v-if="!groupes.length"
                    class="rounded-xl border border-dashed bg-white/50 py-12 text-center text-muted-foreground dark:border-white/10"
                >
                    Aucun groupe de doublons trouvé.
                </div>
            </div>
        </div>

        <!-- Modal Fusion des profils clients -->
        <Dialog :open="modalFusionOpen" @update:open="modalFusionOpen = $event">
            <DialogContent class="sm:max-w-2xl" :show-close-button="true">
                <DialogHeader class="text-left">
                    <DialogTitle class="flex items-center gap-2 text-xl">
                        <UsersRound class="size-6 text-[#459cd1]" />
                        Fusion des profils clients
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- Avertissement -->
                    <div class="flex gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
                        <AlertCircle class="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div class="text-sm">
                            <p class="font-semibold text-amber-800 dark:text-amber-200">Attention : Action irréversible</p>
                            <p class="mt-1 text-amber-700 dark:text-amber-300">
                                Cette opération fusionnera les deux profils en un seul. Toutes les commandes du profil dupliqué seront rattachées au profil principal.
                            </p>
                        </div>
                    </div>

                    <!-- Comparaison des profils -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border-2 border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-800 dark:bg-emerald-900/20">
                            <span class="mb-3 inline-block rounded bg-emerald-600 px-2 py-1 text-xs font-medium text-white">
                                Profil principal (conservé)
                            </span>
                            <template v-if="principalPourModal">
                                <p class="font-semibold text-foreground">{{ nomComplet(principalPourModal) }}</p>
                                <p class="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                                    <Phone class="size-4 shrink-0" />
                                    <span class="font-medium">Téléphone principal :</span> {{ principalPourModal.tel }}
                                </p>
                                <p class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Phone class="size-4 shrink-0" />
                                    <span class="font-medium">Téléphone secondaire :</span>
                                    {{ premierDuplique && optionNumeroSecondaire ? premierDuplique.tel : (principalPourModal.tel || '—') }}
                                </p>
                                <p class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <MapPin class="size-4 shrink-0" />
                                    {{ principalPourModal.adresse || '-' }}{{ principalPourModal.zone ? `, ${principalPourModal.zone}` : '' }}
                                </p>
                                <p class="flex items-center gap-2 text-sm">
                                    <ListOrdered class="size-4 shrink-0" />
                                    {{ principalPourModal.nb_commandes }} commandes . {{ Number(principalPourModal.total_depense).toLocaleString('fr-FR') }} xaf
                                </p>
                            </template>
                        </div>
                        <div class="rounded-lg border-2 border-red-200 bg-red-50/50 p-4 dark:border-red-800 dark:bg-red-900/20">
                            <span class="mb-3 inline-block rounded bg-red-600 px-2 py-1 text-xs font-medium text-white">
                                Profil dupliqué (sera fusionné)
                            </span>
                            <template v-if="premierDuplique">
                                <p class="font-semibold text-foreground">{{ nomComplet(premierDuplique) }}</p>
                                <p class="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                                    <Phone class="size-4 shrink-0" />
                                    <span class="font-medium">Téléphone principal :</span> {{ premierDuplique.tel }}
                                </p>
                                <p class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <MapPin class="size-4 shrink-0" />
                                    {{ premierDuplique.adresse || '-' }}{{ premierDuplique.zone ? `, ${premierDuplique.zone}` : '' }}
                                </p>
                                <p class="flex items-center gap-2 text-sm">
                                    <ListOrdered class="size-4 shrink-0" />
                                    {{ premierDuplique.nb_commandes }} commandes . {{ Number(premierDuplique.total_depense).toLocaleString('fr-FR') }} xaf
                                </p>
                            </template>
                            <template v-else-if="dupliquesPourModal.length > 1">
                                <p class="text-sm text-muted-foreground">{{ dupliquesPourModal.length }} profils dupliqués seront fusionnés</p>
                            </template>
                        </div>
                    </div>

                    <!-- Option de fusion -->
                    <div>
                        <h4 class="mb-2 font-semibold text-foreground">Option de fusion</h4>
                        <label class="flex cursor-pointer items-center gap-2 rounded border p-3">
                            <input
                                v-model="optionNumeroSecondaire"
                                type="radio"
                                :value="true"
                                class="size-4"
                            />
                            <span class="text-sm">Ajouter le numéro du profil dupliqué comme numéro secondaire</span>
                        </label>
                        <label class="mt-2 flex cursor-pointer items-center gap-2 rounded border p-3">
                            <input
                                v-model="optionNumeroSecondaire"
                                type="radio"
                                :value="false"
                                class="size-4"
                            />
                            <span class="text-sm">Ne pas ajouter le numéro secondaire</span>
                        </label>
                    </div>

                    <!-- Résumé de la fusion -->
                    <div>
                        <h4 class="mb-2 font-semibold text-foreground">Résumé de la fusion</h4>
                        <ul class="space-y-1.5 text-sm text-muted-foreground">
                            <li v-if="principalPourModal" class="flex items-center gap-2">
                                <CheckCheck class="size-4 shrink-0 text-[#459cd1]" />
                                Profil conservé : {{ nomComplet(principalPourModal) }}
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCheck class="size-4 shrink-0 text-[#459cd1]" />
                                {{ commandesRattachees }} commande(s) seront rattachées au profil principal
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCheck class="size-4 shrink-0 text-[#459cd1]" />
                                Total après fusion : {{ totalApresFusion.commandes }} commandes, {{ Number(totalApresFusion.montant).toLocaleString('fr-FR') }} xaf
                            </li>
                        </ul>
                    </div>

                    <!-- Boutons -->
                    <DialogFooter class="flex-row justify-between border-t pt-4 sm:justify-between">
                        <Button
                            variant="destructive"
                            class="bg-red-600 text-white hover:bg-red-700"
                            @click="fermerModalFusion"
                        >
                            Annuler
                        </Button>
                        <Button
                            class="bg-[#459cd1] text-white hover:bg-[#3a8ab8]"
                            @click="confirmerFusion"
                        >
                            Fusionner les profils
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>

        <ConfirmModal
            :open="showIgnorerModal"
            title="Marquer comme ignoré"
            :description="groupeToIgnorer ? `Marquer le groupe #${groupeToIgnorer.numero} comme ignoré ? Ces clients seront considérés comme distincts.` : ''"
            confirm-text="Ignorer"
            variant="default"
            @update:open="showIgnorerModal = $event"
            @confirm="confirmIgnorer"
        />
    </AppLayout>
</template>
