<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Check,
    ChevronDown,
    Coins,
    Eye,
    Info,
    Percent,
    Phone,
    Plus,
    ShoppingBag,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { dashboard } from '@/routes';

type MoisOption = { value: string; label: string };
type VenteLigne = {
    date: string;
    produit: string;
    categorie: string;
    montant: number;
    commande_utilise_credit: boolean;
};
type HistoriqueItem = {
    mois: string;
    periode: string;
    montant: number;
    statut: string;
    statut_label: string;
};
type CommandeRecente = {
    numero: string;
    client: string;
    montant: number;
    statut: string;
    statut_slug: string;
    credit_utilise: boolean;
};

const props = withDefaults(
    defineProps<{
    context?: 'admin' | 'pharmacie';
    pharmacie_id?: number | null;
    pharmacie?: {
        id: number;
        designation: string;
        telephone?: string;
        email?: string | null;
    } | null;
    mois: string;
    mois_label: string;
    mois_options: MoisOption[];
    config: {
        commission_percent: number;
        commission_jour_echeance: number;
        periode_jour_fin: number;
        credit_seuil_medicament_xaf: number;
        credit_prix_unitaire_xaf: number;
        credit_minimum_achat: number;
        produit_types: string[];
    };
    kpis: {
        nb_commandes: number;
        ca_parapharma: number;
        credits_disponibles: number;
        credits_utilises: number;
        credits_prepayes_total: number;
        credits_consommes_total: number;
        cout_credits_consommes: number;
        commandes_eligibles_credit: number;
        montant_commission: number;
    };
    commission_courante: {
        periode_label: string;
        echeance_label: string;
        montant: number;
        statut: string;
        statut_label: string;
        paye_le: string | null;
    };
    ventes: VenteLigne[];
    historique_commissions: HistoriqueItem[];
    commandes_recentes: CommandeRecente[];
}>(),
    {
        context: 'admin',
        pharmacie_id: null,
        pharmacie: null,
    },
);

const moisDropdownOpen = ref(false);
const rechargeModalOpen = ref(false);

const isPharmacie = computed(() => props.context === 'pharmacie');

const commandesHref = computed(() =>
    isPharmacie.value ? '/dok-pharma/commandes' : '/commandes',
);

const contactTel = computed(
    () => props.pharmacie?.telephone?.trim() || '+242000000000',
);

const creditProgressPct = computed(() => {
    const denom = Math.max(
        props.kpis.credits_prepayes_total,
        props.kpis.credits_utilises + props.kpis.credits_disponibles,
        1,
    );
    return Math.min(
        100,
        Math.round((props.kpis.credits_utilises / denom) * 100),
    );
});

const creditProgressLabel = computed(() => {
    const denom = Math.max(
        props.kpis.credits_prepayes_total,
        props.kpis.credits_utilises + props.kpis.credits_disponibles,
    );
    return `${props.kpis.credits_utilises} / ${denom} crédits utilisés ce mois`;
});

const payForm = useForm({ mois: props.mois });

const rechargeForm = useForm({
    nombre_credits: '',
    mode_paiement: 'solde_interne',
    note: '',
});

function dashboardQuery(mois: string): Record<string, string | number> {
    if (isPharmacie.value && props.pharmacie_id) {
        return { mois, pharmacie_id: props.pharmacie_id };
    }
    return { mois, tab: 'parapharma' };
}

function setMois(value: string) {
    moisDropdownOpen.value = false;
    const url = isPharmacie.value ? '/dok-pharma' : dashboard();
    router.get(url, dashboardQuery(value), { preserveState: true });
}

function marquerPaye() {
    const url = isPharmacie.value
        ? '/dok-pharma/commission/payee'
        : '/dashboard/commission/payee';
    payForm.post(url, { preserveScroll: true });
}

function submitRecharge() {
    rechargeForm.post('/dok-pharma/credits/recharge', {
        preserveScroll: true,
        onSuccess: () => {
            rechargeModalOpen.value = false;
            rechargeForm.reset();
        },
    });
}

function formatXaf(n: number): string {
    return Number(n).toLocaleString('fr-FR');
}

function statutBadgeClass(statut: string): string {
    if (statut === 'Livré' || statut === 'Payé') {
        return 'bg-[#198754] text-white';
    }
    if (statut === 'Annulée') {
        return 'bg-gray-400 text-white';
    }
    return 'bg-[#FD7E14] text-white';
}
</script>

<template>
    <div class="space-y-6">
        <!-- KPIs -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm"
            >
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-600">
                            Commandes
                        </p>
                        <p class="text-xs text-gray-500">(mois en cours)</p>
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-[#E8F5E9]"
                    >
                        <ShoppingBag class="size-5 text-[#198754]" />
                    </div>
                </div>
                <p class="text-3xl font-extrabold text-gray-900">
                    {{ kpis.nb_commandes }}
                    <span class="text-base font-bold">commandes</span>
                </p>
                <Link
                    :href="commandesHref"
                    class="mt-3 inline-block text-sm font-semibold text-[#198754] hover:underline"
                >
                    Voir toutes les commandes
                </Link>
            </div>

            <div
                class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm"
            >
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-600">
                            CA généré
                        </p>
                        <p class="text-xs text-gray-500">(parapharmacie)</p>
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-[#E8F5E9]"
                    >
                        <BarChart3 class="size-5 text-[#198754]" />
                    </div>
                </div>
                <p class="text-3xl font-extrabold text-gray-900">
                    {{ formatXaf(kpis.ca_parapharma) }}
                    <span class="text-base font-bold">XAF</span>
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    CA articles parapharmacie (lignes vente)
                </p>
            </div>

            <div
                class="rounded-2xl border-2 border-[#E9D5FF] bg-gradient-to-br from-[#FAF5FF] to-white p-5 shadow-sm"
            >
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-[#6B21A8]">
                            Crédits disponibles
                        </p>
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-[#F3E8FF]"
                    >
                        <Coins class="size-5 text-[#7C3AED]" />
                    </div>
                </div>
                <p class="text-3xl font-extrabold text-[#6B21A8]">
                    {{ kpis.credits_disponibles }}
                    <span class="text-base font-bold">crédits</span>
                </p>
                <p class="mt-1 text-xs text-gray-600">
                    1 crédit = 1 commande médicaments ≥
                    {{ formatXaf(config.credit_seuil_medicament_xaf) }} XAF
                </p>
                <p class="text-xs text-gray-600">
                    {{ formatXaf(config.credit_prix_unitaire_xaf) }} XAF /
                    crédit · achat min.
                    {{ config.credit_minimum_achat }} crédits
                </p>
                <div class="mt-3">
                    <div
                        class="h-2 overflow-hidden rounded-full bg-[#E9D5FF]"
                    >
                        <div
                            class="h-full rounded-full bg-[#8B5CF6] transition-all"
                            :style="{ width: `${creditProgressPct}%` }"
                        />
                    </div>
                    <p class="mt-1 text-xs font-medium text-[#6B21A8]">
                        {{ creditProgressLabel }}
                    </p>
                </div>
                <button
                    v-if="isPharmacie"
                    type="button"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#7C3AED] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#6D28D9]"
                    @click="rechargeModalOpen = true"
                >
                    <Plus class="size-4" />
                    Recharger mes crédits
                </button>
            </div>

            <div
                class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm"
            >
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-600">
                            Commission ({{ config.commission_percent }}%)
                        </p>
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-[#E8F5E9]"
                    >
                        <Percent class="size-5 text-[#198754]" />
                    </div>
                </div>
                <p class="text-3xl font-extrabold text-gray-900">
                    {{ formatXaf(kpis.montant_commission) }}
                    <span class="text-base font-bold">XAF</span>
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    À verser pour la période
                </p>
            </div>
        </div>

        <!-- Bandeau commission -->
        <div
            class="rounded-2xl border border-[#BBF7D0] bg-[#F0FDF4] p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h2
                        class="flex items-center gap-2 text-lg font-bold text-gray-900"
                    >
                        <Percent class="size-5 text-[#198754]" />
                        Commission Parapharmacie ({{
                            config.commission_percent
                        }}%)
                        <Info class="size-4 text-gray-400" />
                    </h2>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold text-gray-500">
                                Période
                            </p>
                            <p class="font-bold text-gray-900">
                                {{ commission_courante.periode_label }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500">
                                Échéance
                            </p>
                            <p class="font-bold text-[#FD7E14]">
                                {{ commission_courante.echeance_label }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-6">
                    <div class="text-center sm:text-right">
                        <p class="text-xs font-semibold text-gray-500">
                            Total à verser
                        </p>
                        <p class="text-3xl font-extrabold text-[#198754]">
                            {{ formatXaf(commission_courante.montant) }} XAF
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500">
                            Statut
                        </p>
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-sm font-bold"
                            :class="
                                commission_courante.statut === 'paye'
                                    ? 'bg-[#198754] text-white'
                                    : 'bg-[#FD7E14] text-white'
                            "
                        >
                            {{ commission_courante.statut_label }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                        >
                            <Eye class="size-4" />
                            Voir détails
                        </button>
                        <button
                            v-if="commission_courante.statut !== 'paye'"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-[#198754] px-4 py-2 text-sm font-semibold text-white hover:bg-[#157347] disabled:opacity-50"
                            :disabled="payForm.processing"
                            @click="marquerPaye"
                        >
                            <Check class="size-4" />
                            Marquer comme payé
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ventes + crédits -->
        <div class="grid gap-6 lg:grid-cols-3">
            <div
                class="lg:col-span-2 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">
                        Détail des ventes (parapharmacie)
                    </h3>
                    <div class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold"
                            @click="moisDropdownOpen = !moisDropdownOpen"
                        >
                            Afficher : {{ mois_label }}
                            <ChevronDown class="size-4" />
                        </button>
                        <div
                            v-show="moisDropdownOpen"
                            class="absolute right-0 top-full z-20 mt-1 min-w-[180px] rounded-lg border bg-white py-1 shadow-lg"
                        >
                            <button
                                v-for="opt in mois_options"
                                :key="opt.value"
                                type="button"
                                class="w-full px-4 py-2 text-left text-sm hover:bg-gray-100"
                                @click="setMois(opt.value)"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs font-bold text-gray-500">
                                <th class="pb-3 pr-4">Date</th>
                                <th class="pb-3 pr-4">Produit</th>
                                <th class="pb-3 pr-4">Catégorie</th>
                                <th class="pb-3 pr-4 text-right">Montant (XAF)</th>
                                <th class="pb-3 text-center">
                                    Éligible crédit
                                </th>
                                <th class="pb-3 text-center">
                                    Crédit utilisé
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-if="ventes.length === 0"
                                class="text-gray-500"
                            >
                                <td colspan="6" class="py-8 text-center">
                                    Aucune vente sur la période
                                </td>
                            </tr>
                            <tr
                                v-for="(v, i) in ventes"
                                :key="i"
                                class="border-b border-gray-50"
                            >
                                <td class="py-3 pr-4 text-gray-700">
                                    {{ v.date }}
                                </td>
                                <td
                                    class="max-w-[200px] truncate py-3 pr-4 font-medium text-gray-900"
                                    :title="v.produit"
                                >
                                    {{ v.produit }}
                                </td>
                                <td class="py-3 pr-4 text-gray-600">
                                    {{ v.categorie }}
                                </td>
                                <td class="py-3 pr-4 text-right font-semibold">
                                    {{ formatXaf(v.montant) }}
                                </td>
                                <td class="py-3 text-center">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold"
                                        :class="
                                            v.commande_utilise_credit
                                                ? 'bg-[#198754] text-white'
                                                : 'bg-gray-200 text-gray-600'
                                        "
                                    >
                                        {{
                                            v.commande_utilise_credit
                                                ? 'Oui'
                                                : 'Non'
                                        }}
                                    </span>
                                </td>
                                <td class="py-3 text-center text-sm font-semibold text-gray-800">
                                    {{ v.commande_utilise_credit ? '1' : '—' }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="ventes.length > 0">
                            <tr>
                                <td
                                    colspan="3"
                                    class="pt-4 text-right text-sm font-bold text-gray-700"
                                >
                                    TOTAL COMMISSION ({{
                                        config.commission_percent
                                    }}&nbsp;% du CA parapharma)
                                </td>
                                <td
                                    colspan="3"
                                    class="pt-4 text-right text-lg font-extrabold text-[#198754]"
                                >
                                    {{ formatXaf(kpis.montant_commission) }} XAF
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div
                    class="rounded-2xl border-2 border-[#E9D5FF] bg-[#FAF5FF] p-5"
                >
                    <h3 class="mb-4 font-bold text-[#6B21A8]">
                        Consommation des crédits
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-800">
                        <li class="flex justify-between">
                            <span>Crédits utilisés (ce mois)</span>
                            <span class="font-bold">{{
                                kpis.credits_utilises
                            }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span>Coût total</span>
                            <span class="font-bold"
                                >{{ formatXaf(kpis.cout_credits_consommes) }}
                                XAF</span
                            >
                        </li>
                        <li class="flex justify-between">
                            <span>Commandes éligibles</span>
                            <span class="font-bold">{{
                                kpis.commandes_eligibles_credit
                            }}</span>
                        </li>
                    </ul>
                </div>
                <div
                    class="rounded-2xl border border-[#BFDBFE] bg-[#EFF6FF] p-5"
                >
                    <h3 class="mb-2 flex items-center gap-2 font-bold text-[#1D4ED8]">
                        <Info class="size-4" />
                        Comment ça fonctionne ?
                    </h3>
                    <p class="text-sm text-gray-700">
                        Un crédit est utilisé pour chaque commande dont le
                        montant médicaments est supérieur ou égal à
                        {{ formatXaf(config.credit_seuil_medicament_xaf) }}
                        XAF. Chaque crédit correspond à une commission de
                        {{ formatXaf(config.credit_prix_unitaire_xaf) }} XAF.
                    </p>
                </div>
            </div>
        </div>

        <!-- Historique + commandes récentes -->
        <div class="grid gap-6 lg:grid-cols-2">
            <div
                class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"
            >
                <h3 class="mb-4 text-lg font-bold text-gray-900">
                    Historique des commissions
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs font-bold text-gray-500">
                                <th class="pb-3 pr-3">Mois</th>
                                <th class="pb-3 pr-3">Période</th>
                                <th class="pb-3 pr-3 text-right">Montant</th>
                                <th class="pb-3 pr-3">Statut</th>
                                <th class="pb-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(h, i) in historique_commissions"
                                :key="i"
                                class="border-b border-gray-50"
                            >
                                <td class="py-3 pr-3 font-medium">
                                    {{ h.mois }}
                                </td>
                                <td class="py-3 pr-3 text-gray-600">
                                    {{ h.periode }}
                                </td>
                                <td class="py-3 pr-3 text-right font-semibold">
                                    {{ formatXaf(h.montant) }} XAF
                                </td>
                                <td class="py-3 pr-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold"
                                        :class="statutBadgeClass(h.statut_label)"
                                    >
                                        {{ h.statut_label }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <button
                                        type="button"
                                        class="text-gray-500 hover:text-gray-800"
                                        aria-label="Voir"
                                    >
                                        <Eye class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"
            >
                <h3 class="mb-4 text-lg font-bold text-gray-900">
                    Commandes récentes
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs font-bold text-gray-500">
                                <th class="pb-3 pr-3">N° Commande</th>
                                <th class="pb-3 pr-3">Client</th>
                                <th class="pb-3 pr-3 text-right">Montant</th>
                                <th class="pb-3 pr-3">Statut</th>
                                <th class="pb-3">Crédit utilisé</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-if="commandes_recentes.length === 0"
                                class="text-gray-500"
                            >
                                <td colspan="5" class="py-6 text-center">
                                    Aucune commande
                                </td>
                            </tr>
                            <tr
                                v-for="(c, i) in commandes_recentes"
                                :key="i"
                                class="border-b border-gray-50"
                            >
                                <td class="py-3 pr-3 font-medium text-[#198754]">
                                    {{ c.numero }}
                                </td>
                                <td class="py-3 pr-3">{{ c.client }}</td>
                                <td class="py-3 pr-3 text-right font-semibold">
                                    {{ formatXaf(c.montant) }} XAF
                                </td>
                                <td class="py-3 pr-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold"
                                        :class="statutBadgeClass(c.statut)"
                                    >
                                        {{ c.statut }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <span
                                        v-if="c.credit_utilise"
                                        class="inline-flex rounded-full bg-[#198754] px-2 py-0.5 text-xs font-bold text-white"
                                    >
                                        Oui
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Link
                    :href="commandesHref"
                    class="mt-4 inline-block w-full rounded-lg border border-[#198754] py-2.5 text-center text-sm font-bold text-[#198754] hover:bg-[#F0FDF4]"
                >
                    Voir toutes les commandes
                </Link>
            </div>
        </div>

        <!-- Rappel + contact -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-[#FED7AA] bg-[#FFF7ED] p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <AlertTriangle class="mt-0.5 size-5 shrink-0 text-[#EA580C]" />
                <p class="text-sm text-gray-800">
                    <strong>Rappel important :</strong>
                    Les commissions parapharmacie (période du 1er au
                    {{ config.periode_jour_fin }}) doivent être réglées avant le
                    {{ config.commission_jour_echeance }} du mois en cours.
                </p>
            </div>
            <a
                :href="`tel:${contactTel.replace(/\s/g, '')}`"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#198754] px-5 py-3 text-sm font-bold text-white hover:bg-[#157347]"
            >
                <Phone class="size-4" />
                Nous contacter
            </a>
        </div>

        <!-- Modal recharge crédits (pharmacie) -->
        <div
            v-if="isPharmacie && rechargeModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="rechargeModalOpen = false"
        >
            <div
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">
                        Recharger mes crédits
                    </h3>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-gray-500 hover:bg-gray-100"
                        @click="rechargeModalOpen = false"
                    >
                        <X class="size-5" />
                    </button>
                </div>
                <form class="space-y-4" @submit.prevent="submitRecharge">
                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold text-gray-700"
                            >Nombre de crédits</label
                        >
                        <input
                            v-model="rechargeForm.nombre_credits"
                            type="number"
                            min="1"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            :placeholder="`Min. ${config.credit_minimum_achat}`"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold text-gray-700"
                            >Mode de paiement</label
                        >
                        <select
                            v-model="rechargeForm.mode_paiement"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option value="solde_interne">
                                Solde interne (BengaDok)
                            </option>
                            <option value="especes">Espèces</option>
                            <option value="virement">Virement bancaire</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold text-gray-700"
                            >Note (optionnel)</label
                        >
                        <textarea
                            v-model="rechargeForm.note"
                            rows="2"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        />
                    </div>
                    <p class="text-xs text-gray-500">
                        {{ formatXaf(config.credit_prix_unitaire_xaf) }} XAF
                        par crédit · achat minimum
                        {{ config.credit_minimum_achat }} crédits
                    </p>
                    <button
                        type="submit"
                        class="w-full rounded-lg bg-[#198754] py-2.5 text-sm font-bold text-white hover:bg-[#157347] disabled:opacity-50"
                        :disabled="rechargeForm.processing"
                    >
                        Confirmer la recharge
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
