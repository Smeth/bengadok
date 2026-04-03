<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Download, Eye, MoreHorizontal, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { CommandeListItem } from '@/types';
import { STATUTS_COMMANDE } from '@/types';

const props = defineProps<{
    commandes: { data: CommandeListItem[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    stats: Record<string, number>;
    filters: { search?: string; status?: string; periode?: string; date?: string };
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


function getClientDisplayName(client: { nom?: string; prenom?: string; sexe?: string } | undefined): string {
    if (!client) return '-';
    const prenom = (client.prenom ?? '').trim();
    const nom = (client.nom ?? '').trim();
    if (!prenom && !nom) return '-';
    const core = prenom === nom ? prenom : [prenom, nom].filter(Boolean).join(' ');
    const civ = civiliteFromSexe(client.sexe);
    return civ ? `${civ} ${core}` : core;
}


function getMedicamentsText(produits: Array<{ designation: string; dosage?: string }> | undefined): string {
    return produits?.map((p) => p.designation + (p.dosage ? ' ' + p.dosage : '')).join(', ') || '-';
}

function formatDate(d: string) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

const statutsList = computed(() => props.statuts ?? STATUTS_COMMANDE);

function getStatusLabel(s: string) {
    if (s === 'a_preparer') return 'Validée';
    if (s === 'retiree') return 'Livrée';
    return statutsList.value.find((st) => st.key === s)?.label ?? s;
}

function getStatusBadgeStyle(s: string) {
    const key = s === 'a_preparer' ? 'validee' : s === 'retiree' ? 'retiree' : s;
    const st = statutsList.value.find((x) => x.key === key);
    if (!st) return { backgroundColor: '#6b7280', color: 'white', border: 'none' };
    return {
        backgroundColor: st.color,
        color: st.textColor,
        border: (st as { borderColor?: string }).borderColor ? `1px solid ${(st as { borderColor?: string }).borderColor}` : 'none',
    };
}

function isNavLink(label: string): boolean {
    return /Previous|Précédent|Next|Suivant|»|&laquo;|&raquo;/.test(label);
}

const nextPageUrl = computed(() => {
    const next = props.commandes.links?.find((l) => /Next|Suivant|»|&raquo;/.test(l.label));
    return next?.url ?? null;
});
</script>

<template>
    <div class="space-y-6">
        <!-- Barre actions groupées -->
        <div
            v-if="someSelected"
            class="flex flex-wrap items-center gap-3 rounded-lg border border-[#0d6efd]/30 bg-[#0d6efd]/10 px-4 py-3"
        >
            <span class="font-medium">{{ selectedIds.size }} commande(s) sélectionnée(s)</span>
            <Button variant="outline" size="sm" @click="emit('clearSelection')">Tout désélectionner</Button>
            <Button variant="outline" size="sm" @click="emit('exportCsv')">
                <Download class="mr-2 size-4" />
                Exporter CSV
            </Button>
            <Button variant="destructive" size="sm" @click="emit('openBulkAnnulerModal')">
                <X class="mr-2 size-4" />
                Annuler
            </Button>
        </div>

        <!-- Tableau -->
        <div class="overflow-x-auto">
            <h3 class="mb-4 text-[20px] font-bold text-black">Liste Commandes</h3>
            <table class="w-full min-w-[1100px] border-collapse text-[14px] text-[rgba(0,0,0,0.74)]">
                <colgroup>
                    <col class="w-10" />
                    <col class="w-[130px]" />
                    <col class="w-[110px]" />
                    <col class="w-[40px]" />
                    <col class="w-[135px]" />
                    <col class="w-[95px]" />
                    <col class="w-[190px]" />
                    <col class="w-[170px]" />
                    <col class="w-[120px]" />
                    <col class="w-[130px]" />
                    <col class="w-[80px]" />
                </colgroup>
                <thead>
                    <tr class="border-b border-[rgba(102,102,102,0.42)] text-[14px] font-extrabold text-[rgba(0,0,0,0.74)]">
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
                        <th class="pb-3 pr-3 text-left font-bold">Médicament</th>
                        <th class="pb-3 pr-3 text-left font-bold">Montant</th>
                        <th class="pb-3 pr-3 text-left font-bold">Statut</th>
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
                        <td class="py-3 pr-3 align-middle font-mono text-[12px] font-medium text-[#0d6efd]">
                            {{ cmd.numero }}
                        </td>
                        <td class="py-3 pr-3 align-middle">
                            <span class="block truncate text-[13px] font-medium" :title="getClientDisplayName(cmd.client)">
                                {{ getClientDisplayName(cmd.client) }}
                            </span>
                        </td>
                        <td class="py-3 pr-3 align-middle text-center text-[15px]">
                            {{ cmd.client?.sexe || '-' }}
                        </td>
                        <td class="py-3 pr-3 align-middle font-mono text-[12px]">
                            {{ cmd.client?.tel ?? '-' }}
                        </td>
                        <td class="py-3 pr-3 align-middle text-[12px] text-gray-600">
                            {{ formatDate(cmd.date) }}
                        </td>
                        <td class="py-3 pr-3 align-middle">
                            <span class="block truncate text-[11px] text-gray-600" :title="cmd.client?.adresse ?? ''">
                                {{ cmd.client?.adresse || '-' }}
                            </span>
                        </td>
                        <td class="py-3 pr-3 align-middle">
                            <span class="block truncate text-[11px] text-gray-600" :title="getMedicamentsText(cmd.produits)">
                                {{ getMedicamentsText(cmd.produits) }}
                            </span>
                        </td>
                        <td class="py-3 pr-3 align-middle">
                            <span class="whitespace-nowrap font-mono text-[15px] font-bold text-[rgba(0,0,0,0.74)]">
                                {{ Number(cmd.prix_total).toLocaleString('fr-FR') }}&nbsp;FCFA
                            </span>
                        </td>
                        <td class="py-3 pr-3 align-middle">
                            <span
                                class="inline-flex whitespace-nowrap rounded-[10px] px-3 py-1.5 text-[13px] font-bold"
                                :style="getStatusBadgeStyle(cmd.status)"
                            >
                                {{ getStatusLabel(cmd.status) }}
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
                                            <MoreHorizontal class="size-4 text-[#5C5959]" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem @click="emit('openDetail', cmd.id)">
                                            Voir détails
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!commandes.data?.length">
                        <td colspan="11" class="py-16 text-center text-[14px] text-gray-400">
                            Aucune commande trouvée
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="commandes.links?.length" class="mt-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <template v-for="(link, i) in commandes.links" :key="i">
                    <Link
                        v-if="link.url && !isNavLink(link.label)"
                        :href="link.url"
                        class="flex h-[31px] min-w-[31px] shrink-0 items-center justify-center rounded-[10px] px-3 text-[13px] font-bold transition-colors"
                        :class="
                            link.active
                                ? 'bg-[#0d6efd] text-white'
                                : 'border border-[rgba(102,102,102,0.42)] bg-white text-[rgba(102,102,102,0.6)] hover:bg-gray-50'
                        "
                    >
                        {{ link.label }}
                    </Link>
                    <span
                        v-else-if="!link.url && !isNavLink(link.label)"
                        class="flex h-[31px] shrink-0 items-center justify-center px-2 text-[13px] text-[rgba(102,102,102,0.35)]"
                    >
                        {{ link.label }}
                    </span>
                </template>
            </div>
            <Link
                v-if="nextPageUrl"
                :href="nextPageUrl"
                class="flex items-center gap-2 text-[14px] font-bold text-[rgba(0,0,0,0.74)] hover:underline"
            >
                Page suivante
                <ChevronRight class="size-4" />
            </Link>
        </div>
    </div>
</template>
