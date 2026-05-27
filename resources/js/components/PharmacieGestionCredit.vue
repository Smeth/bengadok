<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    CheckCircle2,
    Coins,
    CreditCard,
    Eye,
    Info,
    Pencil,
    Plus,
    Settings,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type HistoriqueOp = {
    id: number;
    date: string;
    type: string;
    type_label: string;
    description: string;
    credits_affichage: string;
    cout_xaf: number;
    solde_apres: number;
    utilisateur: string;
};

const props = defineProps<{
    pharmacieId: number;
    pharmacie: {
        designation: string;
        adresse: string;
        telephone: string;
        email: string | null;
        de_garde: boolean;
    };
    creditGestion: {
        resume: {
            credits_disponibles: number;
            valeur_disponible_xaf: number;
            credits_utilises_mois: number;
            cout_mois_xaf: number;
            statut: string;
            statut_label: string;
            statut_detail: string;
        };
        config: {
            prix_unitaire_xaf: number;
            seuil_medicament_xaf: number;
            minimum_achat: number;
            deduction_auto: boolean;
            alerte_seuil: number;
            alerte_seuil_pharmacie: number | null;
        };
        modes_paiement: Array<{ value: string; label: string }>;
        historique: HistoriqueOp[];
        note_interne: string | null;
        note_modifie_le: string | null;
    };
}>();

const rechargeForm = useForm({
    nombre_credits: '',
    mode_paiement: 'solde_interne',
    note: '',
});

const noteForm = useForm({
    note_interne: props.creditGestion.note_interne ?? '',
});

const alerteForm = useForm({
    credits_alerte_seuil: String(
        props.creditGestion.config.alerte_seuil_pharmacie ??
            props.creditGestion.config.alerte_seuil,
    ),
});

const editNote = ref(false);
const editAlerte = ref(false);
const showAlerteModal = ref(false);

const coutRecharge = computed(() => {
    const n = parseInt(rechargeForm.nombre_credits, 10);
    if (!Number.isFinite(n) || n <= 0) return 0;
    return n * props.creditGestion.config.prix_unitaire_xaf;
});

const statutOk = computed(
    () => props.creditGestion.resume.statut === 'actif',
);

function formatXaf(n: number): string {
    return Number(n).toLocaleString('fr-FR');
}

function submitRecharge() {
    const n = parseInt(rechargeForm.nombre_credits, 10);
    if (!Number.isFinite(n) || n < 1) return;

    rechargeForm.post(`/pharmacies/${props.pharmacieId}/credits/recharge`, {
        preserveScroll: true,
        onSuccess: () => {
            rechargeForm.reset();
            rechargeForm.mode_paiement = 'solde_interne';
        },
    });
}

function submitNote() {
    noteForm.patch(`/pharmacies/${props.pharmacieId}/credits/note`, {
        preserveScroll: true,
        onSuccess: () => {
            editNote.value = false;
        },
    });
}

function submitAlerte() {
    const n = parseInt(alerteForm.credits_alerte_seuil, 10);
    alerteForm.transform(() => ({
        credits_alerte_seuil: Number.isFinite(n) && n > 0 ? n : null,
    }));
    alerteForm.patch(`/pharmacies/${props.pharmacieId}/credits/alerte-seuil`, {
        preserveScroll: true,
        onSuccess: () => {
            editAlerte.value = false;
            showAlerteModal.value = false;
        },
    });
}
</script>

<template>
    <div class="space-y-6">
        <!-- En-tête pharmacie + KPIs -->
        <div
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
        >
            <div
                class="mb-5 flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-5"
            >
                <div>
                    <h2 class="text-xl font-bold text-gray-900">
                        {{ pharmacie.designation }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ pharmacie.adresse }}
                    </p>
                    <p class="text-sm text-gray-600">
                        {{ pharmacie.telephone }}
                        <span v-if="pharmacie.email">
                            · {{ pharmacie.email }}</span
                        >
                    </p>
                    <span
                        class="mt-2 inline-flex rounded-full px-3 py-0.5 text-xs font-bold"
                        :class="
                            pharmacie.de_garde
                                ? 'bg-red-500 text-white'
                                : 'bg-emerald-100 text-emerald-800'
                        "
                    >
                        {{ pharmacie.de_garde ? 'De garde' : 'Active' }}
                    </span>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    class="rounded-xl border border-purple-100 bg-purple-50/50 p-4"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-semibold text-purple-900"
                            >Crédits disponibles</span
                        >
                        <Coins class="size-5 text-purple-600" />
                    </div>
                    <p class="text-2xl font-extrabold text-purple-900">
                        {{ creditGestion.resume.credits_disponibles }}
                        <span class="text-base font-bold">crédits</span>
                    </p>
                    <p class="text-sm text-purple-700">
                        ({{ formatXaf(creditGestion.resume.valeur_disponible_xaf) }}
                        XAF)
                    </p>
                </div>

                <div
                    class="rounded-xl border border-blue-100 bg-blue-50/50 p-4"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-semibold text-blue-900"
                            >Crédits utilisés sur la période</span
                        >
                        <BarChart3 class="size-5 text-blue-600" />
                    </div>
                    <p class="text-2xl font-extrabold text-blue-900">
                        {{ creditGestion.resume.credits_utilises_mois }}
                        <span class="text-base font-bold">crédits</span>
                    </p>
                    <p class="text-sm text-blue-700">
                        ({{ formatXaf(creditGestion.resume.cout_mois_xaf) }}
                        XAF)
                    </p>
                </div>

                <div
                    class="rounded-xl border border-amber-100 bg-amber-50/50 p-4"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-semibold text-amber-900"
                            >Coût total sur la période</span
                        >
                        <CreditCard class="size-5 text-amber-600" />
                    </div>
                    <p class="text-2xl font-extrabold text-amber-900">
                        {{ formatXaf(creditGestion.resume.cout_mois_xaf) }}
                        <span class="text-base font-bold">XAF</span>
                    </p>
                </div>

                <div
                    class="rounded-xl border p-4"
                    :class="
                        statutOk
                            ? 'border-emerald-100 bg-emerald-50/50'
                            : 'border-orange-100 bg-orange-50/50'
                    "
                >
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="text-sm font-semibold"
                            :class="
                                statutOk ? 'text-emerald-900' : 'text-orange-900'
                            "
                            >Statut des crédits</span
                        >
                        <CheckCircle2
                            class="size-5"
                            :class="
                                statutOk ? 'text-emerald-600' : 'text-orange-600'
                            "
                        />
                    </div>
                    <p
                        class="text-2xl font-extrabold"
                        :class="
                            statutOk ? 'text-emerald-900' : 'text-orange-900'
                        "
                    >
                        {{ creditGestion.resume.statut_label }}
                    </p>
                    <p
                        class="text-sm"
                        :class="
                            statutOk ? 'text-emerald-700' : 'text-orange-700'
                        "
                    >
                        {{ creditGestion.resume.statut_detail }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Colonne principale -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Recharge -->
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="mb-4 text-lg font-bold text-gray-900">
                        Ajouter / Recharger des crédits
                    </h3>
                    <form
                        class="space-y-4"
                        @submit.prevent="submitRecharge"
                    >
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-gray-700"
                                    >Nombre de crédits à ajouter</label
                                >
                                <div class="flex">
                                    <input
                                        v-model="rechargeForm.nombre_credits"
                                        type="number"
                                        min="1"
                                        placeholder="Ex: 50"
                                        required
                                        class="w-full rounded-l-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400"
                                    />
                                    <span
                                        class="flex items-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-600"
                                        >crédits</span
                                    >
                                </div>
                                <p
                                    v-if="rechargeForm.errors.nombre_credits"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ rechargeForm.errors.nombre_credits }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-gray-700"
                                    >Coût total</label
                                >
                                <div
                                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-lg font-bold text-emerald-800"
                                >
                                    {{ formatXaf(coutRecharge) }} XAF
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    Min.
                                    {{ creditGestion.config.minimum_achat }}
                                    crédits
                                </p>
                            </div>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Mode de paiement</label
                            >
                            <select
                                v-model="rechargeForm.mode_paiement"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400"
                            >
                                <option
                                    v-for="m in creditGestion.modes_paiement"
                                    :key="m.value"
                                    :value="m.value"
                                >
                                    {{ m.label }}
                                </option>
                            </select>
                        </div>

                        <div
                            class="flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900"
                        >
                            <Info class="mt-0.5 size-4 shrink-0" />
                            <span>
                                1 crédit = 1 commande médicaments ≥
                                {{ formatXaf(creditGestion.config.seuil_medicament_xaf) }}
                                XAF | Coût :
                                {{ formatXaf(creditGestion.config.prix_unitaire_xaf) }}
                                XAF / crédit
                            </span>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Note (optionnel)</label
                            >
                            <textarea
                                v-model="rechargeForm.note"
                                rows="2"
                                placeholder="Ex: Recharge mensuelle"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400"
                            />
                        </div>

                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                                @click="
                                    rechargeForm.reset();
                                    rechargeForm.mode_paiement =
                                        'solde_interne';
                                "
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                :disabled="rechargeForm.processing"
                            >
                                <Plus class="size-4" />
                                Ajouter les crédits
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Historique -->
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="mb-4 text-lg font-bold text-gray-900">
                        Historique des opérations de crédits
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead>
                                <tr
                                    class="border-b text-xs font-bold uppercase text-gray-500"
                                >
                                    <th class="pb-3 pr-3">Date</th>
                                    <th class="pb-3 pr-3">Type</th>
                                    <th class="pb-3 pr-3">Description</th>
                                    <th class="pb-3 pr-3 text-center">
                                        Crédits
                                    </th>
                                    <th class="pb-3 pr-3 text-right">
                                        Coût (XAF)
                                    </th>
                                    <th class="pb-3 pr-3 text-center">
                                        Solde après
                                    </th>
                                    <th class="pb-3 pr-3">Utilisateur</th>
                                    <th class="pb-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-if="creditGestion.historique.length === 0"
                                >
                                    <td
                                        colspan="8"
                                        class="py-8 text-center text-gray-500"
                                    >
                                        Aucune opération enregistrée
                                    </td>
                                </tr>
                                <tr
                                    v-for="op in creditGestion.historique"
                                    :key="op.id"
                                    class="border-b border-gray-50"
                                >
                                    <td class="py-3 pr-3 text-gray-700">
                                        {{ op.date }}
                                    </td>
                                    <td class="py-3 pr-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold"
                                            :class="
                                                op.type === 'recharge'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-red-100 text-red-800'
                                            "
                                        >
                                            {{ op.type_label }}
                                        </span>
                                    </td>
                                    <td
                                        class="max-w-[180px] truncate py-3 pr-3"
                                        :title="op.description"
                                    >
                                        {{ op.description }}
                                    </td>
                                    <td
                                        class="py-3 pr-3 text-center font-bold"
                                        :class="
                                            op.type === 'recharge'
                                                ? 'text-emerald-700'
                                                : 'text-red-700'
                                        "
                                    >
                                        {{ op.credits_affichage }}
                                    </td>
                                    <td
                                        class="py-3 pr-3 text-right font-medium"
                                    >
                                        {{ formatXaf(op.cout_xaf) }}
                                    </td>
                                    <td
                                        class="py-3 pr-3 text-center font-semibold"
                                    >
                                        {{ op.solde_apres }}
                                    </td>
                                    <td class="py-3 pr-3 text-gray-700">
                                        {{ op.utilisateur }}
                                    </td>
                                    <td class="py-3">
                                        <button
                                            type="button"
                                            class="text-gray-400 hover:text-gray-700"
                                            title="Détail"
                                        >
                                            <Eye class="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <h3 class="mb-4 font-bold text-gray-900">
                        Configuration des crédits
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex justify-between">
                            <span>Prix d'un crédit</span>
                            <span class="font-semibold"
                                >{{ formatXaf(creditGestion.config.prix_unitaire_xaf) }}
                                XAF</span
                            >
                        </li>
                        <li class="flex justify-between">
                            <span>Seuil d'éligibilité</span>
                            <span class="font-semibold"
                                >{{ formatXaf(creditGestion.config.seuil_medicament_xaf) }}
                                XAF</span
                            >
                        </li>
                        <li class="flex justify-between">
                            <span>Déduction automatique</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-bold"
                                :class="
                                    creditGestion.config.deduction_auto
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-gray-200 text-gray-600'
                                "
                            >
                                {{
                                    creditGestion.config.deduction_auto
                                        ? 'Activée'
                                        : 'Désactivée'
                                }}
                            </span>
                        </li>
                        <li class="flex justify-between">
                            <span>Alerte crédits faibles</span>
                            <span class="font-semibold"
                                >{{ creditGestion.config.alerte_seuil }}
                                crédits</span
                            >
                        </li>
                        <li class="flex justify-between">
                            <span>Statut</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-bold"
                                :class="
                                    statutOk
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-orange-100 text-orange-800'
                                "
                            >
                                {{ creditGestion.resume.statut_label }}
                            </span>
                        </li>
                    </ul>
                    <Link
                        href="/settings/parametres?onglet=parapharma"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                    >
                        <Settings class="size-4" />
                        Modifier les paramètres
                    </Link>
                </div>

                <div
                    v-if="!statutOk"
                    class="rounded-xl border border-orange-200 bg-orange-50 p-4"
                >
                    <div class="mb-2 flex items-center gap-2 font-bold text-orange-900">
                        <AlertTriangle class="size-4" />
                        Alerte crédits faibles
                    </div>
                    <p class="mb-3 text-sm text-orange-900">
                        Une alerte est envoyée lorsque le solde passe sous
                        {{ creditGestion.config.alerte_seuil }} crédits.
                    </p>
                    <button
                        type="button"
                        class="text-sm font-semibold text-orange-800 underline"
                        @click="showAlerteModal = true"
                    >
                        Modifier le seuil d'alerte
                    </button>
                </div>

                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-bold text-gray-900">
                            Notes sur la pharmacie
                        </h3>
                        <button
                            type="button"
                            class="text-gray-500 hover:text-gray-800"
                            @click="editNote = !editNote"
                        >
                            <Pencil class="size-4" />
                        </button>
                    </div>
                    <form v-if="editNote" @submit.prevent="submitNote">
                        <textarea
                            v-model="noteForm.note_interne"
                            rows="4"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            placeholder="Ex: Client régulier, préfère être contacté le matin..."
                        />
                        <button
                            type="submit"
                            class="mt-2 rounded-lg bg-[#459cd1] px-3 py-1.5 text-sm font-semibold text-white"
                            :disabled="noteForm.processing"
                        >
                            Enregistrer
                        </button>
                    </form>
                    <p
                        v-else
                        class="text-sm text-gray-600 whitespace-pre-wrap"
                    >
                        {{
                            creditGestion.note_interne ||
                            'Aucune note pour le moment.'
                        }}
                    </p>
                    <p
                        v-if="creditGestion.note_modifie_le"
                        class="mt-2 text-xs text-gray-400"
                    >
                        Dernière modification :
                        {{ creditGestion.note_modifie_le }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Modal seuil alerte -->
        <div
            v-if="showAlerteModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="showAlerteModal = false"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-4 font-bold text-gray-900">
                    Seuil d'alerte crédits faibles
                </h3>
                <form @submit.prevent="submitAlerte">
                    <input
                        v-model="alerteForm.credits_alerte_seuil"
                        type="number"
                        min="1"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    />
                    <p class="mt-2 text-xs text-gray-500">
                        Laisser vide pour utiliser le seuil global ({{
                            creditGestion.config.alerte_seuil
                        }}
                        crédits).
                    </p>
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border px-4 py-2 text-sm"
                            @click="showAlerteModal = false"
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white"
                        >
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
