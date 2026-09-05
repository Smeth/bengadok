<script setup lang="ts">
import {
    Download,
    Eye,
    MoreHorizontal,
    X,
} from 'lucide-vue-next';
import ModulePagination from '@/components/shared/ModulePagination.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateFrLocal } from '@/lib/formatDateLocal';
import { getMedicamentsText } from '@/lib/commandeDetailDisplay';
import {
    modulePrimaryAlertBannerClass,
    modulePrimaryTextClass,
} from '@/lib/bengadokUi';
import type { CommandeListItem } from '@/types';
import {
    STATUTS_COMMANDE,
    commandeStatutBadgeStyle,
    commandeStatutLabel,
} from '@/types';

const props = defineProps<{
    commandes: {
        data: CommandeListItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from?: number | null;
        to?: number | null;
        total?: number;
    };
    stats: Record<string, number>;
    filters: {
        search?: string;
        status?: string;
        periode?: string;
        date?: string;
    };
    statuts?: typeof STATUTS_COMMANDE;
    selectedIds: Set<number>;
    allSelected: boolean;
    someSelected: boolean;
    canCreateCommande: boolean;
}>();

const emit = defineEmits<{
    toggleAll: [];
    toggleOne: [id: number];
    clearSelection: [];
    exportCsv: [];
    openBulkAnnulerModal: [];
    openDetail: [id: number];
    filtrer: [key: string, value: string];
}>();

function civiliteFromSexe(sexe?: string | null): string {
    if (sexe === 'F') return 'Mme';
    if (sexe === 'M') return 'Mr';
    return '';
}

function getClientDisplayName(
    client: { nom?: string; prenom?: string; sexe?: string } | undefined,
): string {
    if (!client) return '-';
    const prenom = (client.prenom ?? '').trim();
    const nom = (client.nom ?? '').trim();
    if (!prenom && !nom) return '-';
    const core =
        prenom === nom ? prenom : [prenom, nom].filter(Boolean).join(' ');
    const civ = civiliteFromSexe(client.sexe);
    return civ ? `${civ} ${core}` : core;
}

function medicamentsListLabel(cmd: CommandeListItem): string {
    if (cmd.medicaments_resume) {
        return cmd.medicaments_resume;
    }
    return getMedicamentsText(cmd.produits);
}

function formatDate(d: string) {
    return formatDateFrLocal(d);
}
</script>

<template>
    <div class="space-y-6 p-4 sm:p-5">
        <!-- Barre actions groupées -->
        <div
            v-if="someSelected"
            :class="[
                modulePrimaryAlertBannerClass,
                'flex flex-wrap items-center gap-3 px-4 py-3',
            ]"
        >
            <span class="font-medium"
                >{{ selectedIds.size }} commande(s) sélectionnée(s)</span
            >
            <Button variant="outline" size="sm" @click="emit('clearSelection')"
                >Tout désélectionner</Button
            >
            <Button variant="outline" size="sm" @click="emit('exportCsv')">
                <Download class="mr-2 size-4" />
                Exporter CSV
            </Button>
            <Button
                variant="destructive"
                size="sm"
                @click="emit('openBulkAnnulerModal')"
            >
                <X class="mr-2 size-4" />
                Annuler
            </Button>
        </div>

        <!-- Tableau : largeurs en % pour éviter min-w fixe + scroll horizontal sur le dashboard -->
        <div class="w-full min-w-0 overflow-x-auto">
            <h3 class="mb-4 text-[20px] font-bold text-black">
                Liste Commandes
            </h3>
            <table
                class="w-full min-w-0 max-w-full table-fixed border-collapse text-[14px] text-[rgba(0,0,0,0.74)]"
            >
                <colgroup>
                    <col class="w-[3%]" />
                    <col class="w-[10%]" />
                    <col class="w-[11%]" />
                    <col class="w-[3%]" />
                    <col class="w-[10%]" />
                    <col class="w-[7%]" />
                    <col class="w-[20%]" />
                    <col class="w-[10%]" />
                    <col class="w-[8%]" />
                    <col class="w-[12%]" />
                    <col class="w-[6%]" />
                </colgroup>
                <thead>
                    <tr
                        class="border-b border-[rgba(102,102,102,0.42)] text-[14px] font-extrabold text-[rgba(0,0,0,0.74)]"
                    >
                        <th class="pb-3 pr-3 text-left font-bold">
                            <Checkbox
                                :checked="allSelected"
                                :indeterminate="someSelected && !allSelected"
                                @update:checked="emit('toggleAll')"
                            />
                        </th>
                        <th class="pb-3 pr-3 text-left font-bold">ID Cmd</th>
                        <th class="pb-3 pr-3 text-left font-bold">Client</th>
                        <th class="pb-3 pr-3 text-left font-bold">Sexe</th>
                        <th class="pb-3 pr-3 text-left font-bold">Tel</th>
                        <th class="pb-3 pr-3 text-left font-bold">Date</th>
                        <th class="pb-3 pr-3 text-left font-bold">Adresse</th>
                        <th class="pb-3 pr-3 text-left font-bold">
                            Médicament
                        </th>
                        <th class="pb-3 pr-6 text-left font-bold">Montant</th>
                        <th class="pb-3 pl-2 pr-3 text-left font-bold">Statut</th>
                        <th class="pb-3 text-left font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="min-h-[400px]">
                    <tr
                        v-for="cmd in commandes.data"
                        :key="cmd.id"
                        class="border-b border-[rgba(102,102,102,0.42)] transition-colors hover:bg-gray-50/50"
                    >
                        <td class="py-3 pr-3 align-middle">
                            <Checkbox
                                :checked="selectedIds.has(cmd.id)"
                                @update:checked="emit('toggleOne', cmd.id)"
                            />
                        </td>
                        <td
                            class="max-w-0 py-3 pr-3 align-middle font-mono text-[12px] font-medium whitespace-nowrap"
                            :class="modulePrimaryTextClass"
                        >
                            <span class="block truncate" :title="cmd.numero">{{
                                cmd.numero
                            }}</span>
                        </td>
                        <td class="max-w-0 py-3 pr-3 align-middle">
                            <span
                                class="block min-w-0 truncate text-[13px] font-medium"
                                :title="getClientDisplayName(cmd.client)"
                            >
                                {{ getClientDisplayName(cmd.client) }}
                            </span>
                        </td>
                        <td
                            class="py-3 pr-3 align-middle text-center text-[15px] whitespace-nowrap"
                        >
                            {{ cmd.client?.sexe || '-' }}
                        </td>
                        <td
                            class="py-3 pr-3 align-middle font-mono text-[12px] whitespace-nowrap"
                            :title="cmd.client?.tel ?? undefined"
                        >
                            {{ cmd.client?.tel ?? '-' }}
                        </td>
                        <td
                            class="py-3 pr-3 align-middle text-[12px] whitespace-nowrap text-gray-600"
                        >
                            {{ formatDate(cmd.date) }}
                        </td>
                        <td class="max-w-0 py-3 pr-3 align-middle">
                            <span
                                class="block min-w-0 truncate text-[11px] text-gray-600"
                                :title="cmd.client?.adresse ?? ''"
                            >
                                {{ cmd.client?.adresse || '-' }}
                            </span>
                        </td>
                        <td class="max-w-0 py-3 pr-3 align-middle">
                            <span
                                class="block min-w-0 truncate text-[11px] text-gray-600"
                                :title="medicamentsListLabel(cmd)"
                            >
                                {{ medicamentsListLabel(cmd) }}
                            </span>
                        </td>
                        <td class="py-3 pr-6 align-middle">
                            <span
                                class="inline-block whitespace-nowrap font-mono text-[15px] font-bold text-[rgba(0,0,0,0.74)]"
                            >
                                {{
                                    Number(cmd.prix_total).toLocaleString(
                                        'fr-FR',
                                    )
                                }}&nbsp;FCFA
                            </span>
                        </td>
                        <td
                            class="max-w-0 min-w-0 py-3 pl-2 pr-3 align-middle"
                        >
                            <span
                                class="inline-block max-w-full min-w-0 rounded-[10px] px-2 py-1.5 text-center text-[12px] font-bold leading-snug break-words whitespace-normal"
                                :style="commandeStatutBadgeStyle(cmd.status)"
                                :title="commandeStatutLabel(cmd.status)"
                            >
                                {{ commandeStatutLabel(cmd.status) }}
                            </span>
                        </td>
                        <td class="py-3 align-middle">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="flex size-7 items-center justify-center rounded-md transition-colors hover:bg-gray-100"
                                    title="Voir détails"
                                    @click="emit('openDetail', cmd.id)"
                                >
                                    <Eye class="size-4 text-[#5C5959]" />
                                </button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="flex size-7 items-center justify-center rounded-md transition-colors hover:bg-gray-100"
                                        >
                                            <MoreHorizontal
                                                class="size-4 text-[#5C5959]"
                                            />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            @click="emit('openDetail', cmd.id)"
                                        >
                                            Voir détails
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!commandes.data?.length">
                        <td
                            colspan="11"
                            class="py-16 text-center text-[14px] text-gray-400"
                        >
                            Aucune commande trouvée
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="border-t border-border pt-4">
            <ModulePagination
                :links="commandes.links"
                :from="commandes.from"
                :to="commandes.to"
                :total="commandes.total"
            />
        </div>
    </div>
</template>
