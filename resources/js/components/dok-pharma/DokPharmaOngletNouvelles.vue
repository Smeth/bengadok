<script setup lang="ts">
import { ChevronDown, ChevronUp, Clock, Eye, FileText, ShoppingCart } from 'lucide-vue-next';
import { toRef } from 'vue';
import PharmaciePieceJointeSection from '@/components/dok-pharma/PharmaciePieceJointeSection.vue';
import { useDokPharmaAccordion } from '@/composables/useDokPharmaAccordion';
import { useDokPharmaNouvellesForm } from '@/composables/useDokPharmaNouvellesForm';
import { pharmacyOrderCardClass } from '@/lib/bengadokUi';
import {
    classesStatutDisponibiliteLigne,
    libelleStatutDisponibiliteLigne,
} from '@/lib/commandeProduitStatus';
import { clientNomAvecCivilite } from '@/lib/clientDisplayName';
import {
    nomCommandeVisible,
    peutAjouterPieceJointe,
    type DokPharmaCommande,
} from '@/lib/dokPharmaCommande';

const props = defineProps<{
    commandes: DokPharmaCommande[];
}>();

const emit = defineEmits<{
    'open-ordonnance': [cmd: DokPharmaCommande];
    'envoi-success': [];
}>();

const commandesRef = toRef(props, 'commandes');

const { expandedCards, toggleCard, collapseCard } = useDokPharmaAccordion((cmd) =>
    initForm(cmd),
);

const {
    formLignes,
    formCommentaires,
    initForm,
    totalCmd,
    totalLigne,
    qteInvalide,
    toggleDispo,
    statutDispoForm,
    peutEnvoyerDisponibilite,
    envoyer,
} = useDokPharmaNouvellesForm({
    commandes: commandesRef,
    expandedCards,
    collapseCard,
    onEnvoiSuccess: () => emit('envoi-success'),
});

function openOrdonnance(cmd: DokPharmaCommande) {
    emit('open-ordonnance', cmd);
}
</script>

<template>
    <div
        v-for="cmd in commandes"
        :key="cmd.id"
        :class="pharmacyOrderCardClass"
    >
        <!-- En-tête -->
        <div
            class="flex cursor-pointer items-start justify-between px-5 py-4"
            @click="toggleCard(cmd)"
        >
            <div class="flex-1 min-w-0">
                <div
                    class="mb-1.5 flex flex-wrap items-center gap-2"
                >
                    <span
                        class="text-[15px] font-extrabold text-[#2563EB]"
                    >
                        <template v-if="nomCommandeVisible(cmd)">
                            <span class="font-sans">{{
                                clientNomAvecCivilite(cmd.client!)
                            }}
                                -
                            </span>
                        </template>
                        <span class="font-mono">{{
                            cmd.numero
                        }}</span>
                    </span>
                    <span
                        class="rounded-full bg-[#DBEAFE] px-2.5 py-0.5 text-[10px] font-bold text-[#1D4ED8]"
                        >Nouvelle commande 🔄</span
                    >
                    <span
                        v-if="cmd.ordonnance_id"
                        class="flex items-center gap-1 rounded-full bg-[#E0F2FE] px-2.5 py-0.5 text-[10px] font-bold text-[#0369A1]"
                    >
                        <FileText class="size-3" />Ordonnance
                    </span>
                </div>
                <div
                    class="flex flex-wrap items-center gap-4 text-[12px] text-gray-500"
                >
                    <span class="flex items-center gap-1"
                        ><Paperclip class="size-3.5" />{{
                            cmd.produits?.length ?? 0
                        }}
                        Médicaments demandés</span
                    >
                    <span class="flex items-center gap-1"
                        ><Clock class="size-3.5" />{{
                            cmd.date
                        }}</span
                    >
                </div>
                <p
                    class="mt-1 flex items-center gap-1 text-[12px] font-medium"
                    :class="
                        cmd.ordonnance_id
                            ? 'text-primary'
                            : 'text-gray-400'
                    "
                >
                    <FileText class="size-3.5" />
                    <span v-if="cmd.ordonnance_id"
                        >Ordonnance à vérifier</span
                    >
                    <span v-else>Aucune ordonnance</span>
                </p>
            </div>
            <component
                :is="
                    expandedCards.has(cmd.id)
                        ? ChevronUp
                        : ChevronDown
                "
                class="mt-1 size-5 shrink-0 text-gray-400"
            />
        </div>

        <!-- Corps développé -->
        <div
            v-if="expandedCards.has(cmd.id)"
            class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4 dark:border-border"
        >
            <!-- Box ordonnance -->
            <div
                v-if="cmd.ordonnance_id"
                class="flex items-center justify-between rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-9 items-center justify-center rounded-full bg-[#459cd1]/10"
                    >
                        <FileText
                            class="size-5 text-[#459cd1]"
                        />
                    </div>
                    <div>
                        <p
                            class="text-[13px] font-semibold text-gray-800"
                        >
                            Ordonnance médicale jointe
                        </p>
                        <p
                            class="text-[11px] text-[#F59E0B] font-medium"
                        >
                            À vérifier
                        </p>
                    </div>
                </div>
                <button
                    class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-[11px] font-bold text-white shadow hover:bg-primary/90 transition-colors"
                    @click.stop="openOrdonnance(cmd)"
                >
                    <Eye class="size-3.5" />Voir et vérifier
                </button>
            </div>

            <!-- Tableau médicaments éditable -->
            <div>
                <p
                    class="mb-2.5 flex items-center gap-1.5 text-[12px] font-semibold text-gray-500"
                >
                    <Paperclip class="size-3.5" />Médicaments
                    demandés
                </p>
                <div
                    class="overflow-x-auto rounded-xl border border-gray-100 dark:border-border"
                >
                    <table
                        class="w-full min-w-[820px] text-[13px]"
                    >
                        <thead class="bg-gray-50 dark:bg-muted/40">
                            <tr>
                                <th
                                    class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                >
                                    Nom Médicament
                                </th>
                                <th
                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                >
                                    Qté demandée
                                </th>
                                <th
                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                >
                                    Qté disponible
                                </th>
                                <th
                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                >
                                    Prix unitaire
                                </th>
                                <th
                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                >
                                    Total
                                </th>
                                <th
                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                >
                                    Disponibilité
                                </th>
                                <th
                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                >
                                    En vente libre
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr
                                v-for="p in cmd.produits"
                                :key="p.id"
                                class="transition-opacity"
                                :class="
                                    formLignes[cmd.id]?.[p.id]
                                        ?.dispo === false
                                        ? 'opacity-40'
                                        : 'opacity-100'
                                "
                            >
                                <!-- Nom -->
                                <td class="px-4 py-2.5">
                                    <span
                                        class="inline-block rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[13px] text-gray-800 dark:border-border dark:bg-muted/30 dark:text-foreground"
                                    >
                                        {{ p.designation }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-2.5 text-[13px] font-medium tabular-nums text-gray-700"
                                >
                                    {{ p.pivot.quantite }}
                                </td>
                                <!-- Quantité disponible (saisie si dispo) -->
                                <td class="px-3 py-2.5">
                                    <div
                                        class="flex w-16 flex-col gap-0.5"
                                    >
                                        <input
                                            v-model="
                                                formLignes[
                                                    cmd.id
                                                ][p.id].quantite
                                            "
                                            type="number"
                                            min="1"
                                            :max="
                                                p.pivot.quantite
                                            "
                                            :disabled="
                                                formLignes[
                                                    cmd.id
                                                ]?.[p.id]
                                                    ?.dispo !==
                                                true
                                            "
                                            class="w-full rounded-md border px-2 py-1 text-center text-[13px] transition-colors"
                                            :class="
                                                formLignes[
                                                    cmd.id
                                                ]?.[p.id]
                                                    ?.dispo !==
                                                true
                                                    ? 'border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed'
                                                    : qteInvalide(
                                                            cmd.id,
                                                            p,
                                                        )
                                                      ? 'border-red-400 bg-red-50 text-red-700 focus:outline-none focus:ring-1 focus:ring-red-400'
                                                      : 'border-[#2563eb]/70 bg-[#eff6ff] text-[#1e3a8a] focus:border-[#1d4ed8] focus:outline-none focus:ring-2 focus:ring-[#3b82f6]/40'
                                            "
                                        />
                                        <!-- Hint max -->
                                        <span
                                            v-if="
                                                formLignes[
                                                    cmd.id
                                                ]?.[p.id]
                                                    ?.dispo ===
                                                true
                                            "
                                            class="w-full text-center text-[10px] leading-none"
                                            :class="
                                                qteInvalide(
                                                    cmd.id,
                                                    p,
                                                )
                                                    ? 'text-red-500 font-semibold'
                                                    : 'text-gray-400'
                                            "
                                        >
                                            max
                                            {{
                                                p.pivot.quantite
                                            }}
                                        </span>
                                    </div>
                                </td>
                                <!-- Prix unitaire (éditable si dispo) -->
                                <td class="px-3 py-2.5">
                                    <div
                                        class="flex items-center gap-1"
                                    >
                                        <input
                                            v-model="
                                                formLignes[
                                                    cmd.id
                                                ][p.id].prix
                                            "
                                            type="number"
                                            min="0"
                                            placeholder="Ex : 1000"
                                            :disabled="
                                                formLignes[
                                                    cmd.id
                                                ]?.[p.id]
                                                    ?.dispo !==
                                                true
                                            "
                                            class="w-24 rounded-md border px-2 py-1 text-[13px] transition-colors"
                                            :class="
                                                formLignes[
                                                    cmd.id
                                                ]?.[p.id]
                                                    ?.dispo ===
                                                true
                                                    ? 'border-[#2563eb]/70 bg-[#eff6ff] text-[#1e3a8a] focus:border-[#1d4ed8] focus:outline-none focus:ring-2 focus:ring-[#3b82f6]/40'
                                                    : 'border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed'
                                            "
                                        />
                                        <span
                                            class="text-[11px] text-gray-400 shrink-0"
                                            >xaf</span
                                        >
                                    </div>
                                </td>
                                <!-- Total calculé -->
                                <td class="px-3 py-2.5">
                                    <div
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="text-[13px] font-semibold"
                                            :class="
                                                totalLigne(
                                                    cmd.id,
                                                    p,
                                                )
                                                    ? 'text-gray-900 dark:text-foreground'
                                                    : 'text-gray-300'
                                            "
                                        >
                                            {{
                                                totalLigne(
                                                    cmd.id,
                                                    p,
                                                ) || 'Ex : 1000'
                                            }}
                                        </span>
                                        <span
                                            class="text-[11px] text-gray-400 shrink-0"
                                            >xaf</span
                                        >
                                    </div>
                                </td>
                                <!-- Disponibilité -->
                                <td class="px-3 py-2.5">
                                    <div
                                        class="flex flex-col items-start gap-1.5"
                                    >
                                        <span
                                            class="inline-flex w-fit rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                                            :class="
                                                classesStatutDisponibiliteLigne(
                                                    statutDispoForm(
                                                        cmd.id,
                                                        p.id,
                                                    ),
                                                )
                                            "
                                        >
                                            {{
                                                libelleStatutDisponibiliteLigne(
                                                    statutDispoForm(
                                                        cmd.id,
                                                        p.id,
                                                    ),
                                                )
                                            }}
                                        </span>
                                        <button
                                            type="button"
                                            class="relative inline-flex h-5 w-9 cursor-pointer items-center rounded-full transition-colors focus:outline-none"
                                            :class="
                                                formLignes[
                                                    cmd.id
                                                ][p.id]
                                                    .dispo ===
                                                true
                                                    ? 'bg-[#22C55E]'
                                                    : 'bg-gray-200'
                                            "
                                            :title="
                                                formLignes[
                                                    cmd.id
                                                ][p.id]
                                                    .dispo ===
                                                null
                                                    ? 'En attente — cliquer pour indiquer disponible ou indisponible'
                                                    : undefined
                                            "
                                            @click="
                                                toggleDispo(
                                                    cmd.id,
                                                    p.id,
                                                )
                                            "
                                        >
                                            <span
                                                class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                                :class="
                                                    formLignes[
                                                        cmd.id
                                                    ][p.id]
                                                        .dispo ===
                                                    true
                                                        ? 'translate-x-4'
                                                        : 'translate-x-0.5'
                                                "
                                            />
                                        </button>
                                    </div>
                                </td>
                                <!-- Toggle vente libre -->
                                <td class="px-3 py-2.5">
                                    <button
                                        type="button"
                                        class="relative inline-flex h-5 w-9 cursor-pointer items-center rounded-full transition-colors focus:outline-none"
                                        :class="
                                            formLignes[cmd.id][
                                                p.id
                                            ].venteLibre
                                                ? 'bg-[#22C55E]'
                                                : 'bg-gray-200'
                                        "
                                        @click="
                                            formLignes[cmd.id][
                                                p.id
                                            ].venteLibre =
                                                !formLignes[
                                                    cmd.id
                                                ][p.id]
                                                    .venteLibre
                                        "
                                    >
                                        <span
                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                            :class="
                                                formLignes[
                                                    cmd.id
                                                ][p.id]
                                                    .venteLibre
                                                    ? 'translate-x-4'
                                                    : 'translate-x-0.5'
                                            "
                                        />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Total montant commande -->
            <div class="flex items-baseline gap-2 px-1">
                <span
                    class="text-[13px] font-bold text-gray-700"
                    >Total montant commande :</span
                >
                <span
                    class="text-2xl font-bold text-gray-900 dark:text-foreground"
                    >{{ totalCmd(cmd).toFixed(1) }}</span
                >
                <span class="text-[12px] text-gray-500"
                    >xaf</span
                >
            </div>

            <!-- Commentaire commande (back-office, lecture seule) -->
            <div
                class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-[13px] dark:border-border dark:bg-muted/30"
            >
                <p
                    class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-gray-500"
                >
                    Commentaire (commande)
                </p>
                <p
                    class="whitespace-pre-wrap text-gray-800"
                    :class="
                        cmd.commentaire?.trim()
                            ? ''
                            : 'text-gray-400'
                    "
                >
                    {{
                        cmd.commentaire?.trim() ||
                        'Aucun commentaire du back-office.'
                    }}
                </p>
            </div>

            <!-- Commentaires pharmacien -->
            <div class="space-y-1.5">
                <label
                    :for="`comment-pharma-${cmd.id}`"
                    class="block text-[13px] font-bold tracking-wide text-[#1d4ed8]"
                >
                    Commentaires du pharmacien
                </label>
                <textarea
                    :id="`comment-pharma-${cmd.id}`"
                    v-model="formCommentaires[cmd.id]"
                    placeholder="Informations utiles pour le back-office (facultatif)…"
                    rows="3"
                    class="w-full resize-none rounded-xl border border-[#93c5fd]/80 bg-white px-4 py-3 text-[13px] text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#3b82f6]/35 dark:border-border dark:bg-input dark:text-foreground dark:placeholder:text-muted-foreground"
                />
            </div>

            <!-- Photos jointes (images) -->
            <PharmaciePieceJointeSection
                :commande-id="cmd.id"
                :pieces="cmd.pieces_jointes ?? []"
                :editable="peutAjouterPieceJointe(cmd)"
            />

            <div class="flex items-center justify-between">
                <!-- Message d'erreur quantité -->
                <p
                    v-if="hasQteError(cmd)"
                    class="flex items-center gap-1.5 text-[12px] font-semibold text-red-500"
                >
                    <AlertCircle class="size-4 shrink-0" />
                    La quantité confirmée ne peut pas dépasser
                    la quantité demandée.
                </p>
                <div v-else class="flex-1" />
                <div class="flex flex-col items-end gap-1">
                    <p
                        v-if="hasUnresolvedDispo(cmd)"
                        class="text-[11px] font-medium text-amber-700"
                    >
                        Indiquez la disponibilité de chaque
                        médicament (y compris parapharmacie)
                        avant envoi.
                    </p>
                    <p
                        v-else-if="hasPrixError(cmd)"
                        class="text-[11px] font-medium text-red-500"
                    >
                        Saisissez le prix de tous les
                        médicaments disponibles
                    </p>
                    <button
                        type="button"
                        class="rounded-xl px-6 py-2.5 text-[13px] font-bold shadow transition-colors"
                        :class="
                            peutEnvoyerDisponibilite(cmd)
                                ? 'bg-[#459cd1] text-white hover:bg-[#3a87b8]'
                                : 'cursor-not-allowed bg-gray-300 text-gray-600'
                        "
                        :disabled="
                            !peutEnvoyerDisponibilite(cmd)
                        "
                        @click="envoyer(cmd)"
                    >
                        Envoyer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <p
        v-if="!commandes?.length"
        class="rounded-2xl bg-white/20 py-14 text-center text-[14px] font-medium text-white"
    >
        Aucune nouvelle commande.
    </p>
</template>
