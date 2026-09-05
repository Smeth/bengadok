<script setup lang="ts">
import { ChevronDown, ChevronUp, AlertCircle, Clock, Eye, FileText, ShoppingCart } from 'lucide-vue-next';
import PharmaciePieceJointeSection from '@/components/dok-pharma/PharmaciePieceJointeSection.vue';
import { pharmacyOrderCardClass } from '@/lib/bengadokUi';
import { sousTotalCommandeProduits } from '@/lib/commandeTotals';
import {
    classesStatutDisponibiliteLigne,
    libelleStatutDisponibiliteLigne,
} from '@/lib/commandeProduitStatus';
import { clientNomAvecCivilite } from '@/lib/clientDisplayName';
import {
    nomCommandeVisible,
    qteDisponibleAffichee,
    qteDisponibleNombre,
    estVenteLibreProduit,
    peutAjouterPieceJointe,
    type DokPharmaCommande,
} from '@/lib/dokPharmaCommande';

defineProps<{
    commandes: DokPharmaCommande[];
    expandedCards: Set<number>;
}>();

const emit = defineEmits<{
    'toggle-card': [cmd: DokPharmaCommande];
    'open-ordonnance': [cmd: DokPharmaCommande];
}>();

function totalCommandeValidee(cmd: DokPharmaCommande): number {
    return sousTotalCommandeProduits(cmd.produits);
}

function toggleCard(cmd: DokPharmaCommande) {
    emit('toggle-card', cmd);
}

function openOrdonnance(cmd: DokPharmaCommande) {
    emit('open-ordonnance', cmd);
}
</script>

<template>
    <!-- Bandeau info violet -->
    <div
        class="rounded-xl border border-[#C7D2FE] bg-[#EEF2FF] px-4 py-3"
    >
        <p
            class="flex items-center gap-2 text-[13px] font-bold text-[#3730A3]"
        >
            <AlertCircle class="size-4 shrink-0" />
            Commandes en attente de confirmation
        </p>
        <p class="mt-0.5 text-[12px] text-[#4338CA]">
            Ces commandes ont été envoyées. En attente de
            validation par bengadock avant préparation.
        </p>
    </div>

    <div
        v-for="cmd in commandes"
        :key="cmd.id"
        :class="pharmacyOrderCardClass"
    >
        <div
            class="flex cursor-pointer items-start justify-between px-5 py-4"
            @click="toggleCard(cmd)"
        >
            <div class="flex-1 min-w-0">
                <div
                    class="mb-1.5 flex flex-wrap items-center gap-2"
                >
                    <span
                        class="text-[15px] font-extrabold text-[#4338CA]"
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
                        v-if="
                            cmd.status_pharmacie ===
                            'indisponible'
                        "
                        class="rounded-full bg-[#FEE2E2] px-2.5 py-0.5 text-[10px] font-bold text-[#991B1B]"
                    >
                        Indisponible ⚠
                    </span>
                    <span
                        v-else
                        class="rounded-full bg-[#EEF2FF] px-2.5 py-0.5 text-[10px] font-bold text-[#3730A3]"
                    >
                        En attente de confirmation ⏳
                    </span>
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
                        Médicaments</span
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
                            ? 'text-[#22C55E]'
                            : 'text-gray-400'
                    "
                >
                    <FileText class="size-3.5" />
                    <span v-if="cmd.ordonnance_id"
                        >Ordonnance soumise</span
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

        <!-- Corps développé (lecture seule) -->
        <div
            v-if="expandedCards.has(cmd.id)"
            class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4 dark:border-border"
        >
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
                            class="text-[11px] text-[#22C55E] font-medium"
                        >
                            Soumise
                        </p>
                    </div>
                </div>
                <button
                    class="flex items-center gap-1.5 rounded-lg border border-[#459cd1] px-3 py-1.5 text-[11px] font-bold text-[#459cd1] hover:bg-[#459cd1]/10 transition-colors"
                    @click.stop="openOrdonnance(cmd)"
                >
                    <Eye class="size-3.5" />Voir
                </button>
            </div>
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
                            >
                                <td class="px-4 py-2.5">
                                    <span
                                        class="inline-block rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[13px] dark:border-border dark:bg-muted/30 dark:text-foreground"
                                        :class="
                                            p.pivot.status ===
                                            'indisponible'
                                                ? 'text-red-400 line-through'
                                                : 'text-gray-800'
                                        "
                                    >
                                        {{ p.designation }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-2.5 text-[13px] font-medium tabular-nums text-gray-600"
                                >
                                    {{ p.pivot.quantite }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-[13px] font-medium tabular-nums text-gray-800"
                                >
                                    {{
                                        qteDisponibleAffichee(p)
                                    }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <div
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="text-[13px] font-semibold text-gray-900 dark:text-foreground"
                                            >{{
                                                Number(
                                                    p.pivot
                                                        .prix_unitaire,
                                                ).toLocaleString(
                                                    'fr-FR',
                                                )
                                            }}</span
                                        >
                                        <span
                                            class="text-[11px] text-gray-400"
                                            >xaf</span
                                        >
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="text-[13px] font-bold text-gray-900 dark:text-foreground"
                                            >{{
                                                Number(
                                                    p.pivot
                                                        .prix_unitaire *
                                                        qteDisponibleNombre(
                                                            p,
                                                        ),
                                                ).toLocaleString(
                                                    'fr-FR',
                                                )
                                            }}</span
                                        >
                                        <span
                                            class="text-[11px] text-gray-400"
                                            >xaf</span
                                        >
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                                        :class="
                                            classesStatutDisponibiliteLigne(
                                                p.pivot.status,
                                            )
                                        "
                                    >
                                        {{
                                            libelleStatutDisponibiliteLigne(
                                                p.pivot.status,
                                            )
                                        }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="relative inline-flex h-5 w-9 items-center rounded-full"
                                        :class="
                                            estVenteLibreProduit(p)
                                                ? 'bg-[#22C55E]'
                                                : 'bg-gray-200'
                                        "
                                    >
                                        <span
                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                            :class="
                                                estVenteLibreProduit(p)
                                                    ? 'translate-x-4'
                                                    : 'translate-x-0.5'
                                            "
                                        />
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <p
        v-if="!commandes?.length"
        class="rounded-2xl bg-white/20 py-14 text-center text-[14px] font-medium text-white"
    >
        Aucune commande en attente de confirmation.
    </p>
</template>
