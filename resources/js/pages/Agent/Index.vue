<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { usePolling } from '@/composables/usePolling';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

usePolling();

const props = defineProps<{
    commandes: {
        data: Array<{
            id: number;
            numero: string;
            date: string;
            status: string;
            prix_total: number;
            client: { nom: string; prenom: string; tel: string };
            pharmacie: { designation: string };
        }>;
    };
}>();

const selectedIds = ref<Set<number>>(new Set());
const allSelected = computed(() => {
    const data = props.commandes?.data ?? [];
    return data.length > 0 && data.every((c) => selectedIds.value.has(c.id));
});
const someSelected = computed(() => selectedIds.value.size > 0);

function toggleAll() {
    const data = props.commandes?.data ?? [];
    if (allSelected.value) {
        data.forEach((c) => selectedIds.value.delete(c.id));
    } else {
        data.forEach((c) => selectedIds.value.add(c.id));
    }
    selectedIds.value = new Set(selectedIds.value);
}
function toggleOne(id: number) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
}
function clearSelection() {
    selectedIds.value = new Set();
}
function exportSelectedCSV() {
    const data = props.commandes?.data ?? [];
    const selected = data.filter((c) => selectedIds.value.has(c.id));
    if (!selected.length) return;
    const headers = ['N°', 'Client', 'Pharmacie', 'Date', 'Montant', 'Statut'];
    const rows = selected.map((c) => [
        c.numero,
        `${c.client?.prenom ?? ''} ${c.client?.nom ?? ''}`.trim() +
            ' - ' +
            (c.client?.tel ?? ''),
        c.pharmacie?.designation ?? '-',
        c.date ?? '',
        Number(c.prix_total).toLocaleString('fr-FR'),
        c.status,
    ]);
    const csv = [
        headers.join(';'),
        ...rows.map((r) =>
            r.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(';'),
        ),
    ].join('\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `receptions_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Mes réceptions', href: '/agent' },
];
</script>

<template>
    <Head title="Agent - Réceptions - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Mes réceptions</h1>
                <Link href="/agent/nouvelle-commande">
                    <button
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        Nouvelle commande
                    </button>
                </Link>
            </div>

            <div
                v-if="someSelected"
                class="flex flex-wrap items-center gap-3 rounded-lg border bg-primary/5 px-4 py-2"
            >
                <span class="font-medium"
                    >{{ selectedIds.size }} commande(s) sélectionnée(s)</span
                >
                <Button variant="outline" size="sm" @click="clearSelection"
                    >Tout désélectionner</Button
                >
                <Button variant="outline" size="sm" @click="exportSelectedCSV">
                    <Download class="mr-2 size-4" />
                    Exporter CSV
                </Button>
            </div>

            <div class="rounded-xl border overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="w-10 px-2 py-3">
                                <Checkbox
                                    :checked="allSelected"
                                    :indeterminate="
                                        someSelected && !allSelected
                                    "
                                    @update:checked="toggleAll"
                                />
                            </th>
                            <th class="px-4 py-3 text-left font-medium">N°</th>
                            <th class="px-4 py-3 text-left font-medium">
                                Client
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Pharmacie
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Date
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Montant
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Statut
                            </th>
                            <th class="px-4 py-3 text-left font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="cmd in commandes.data"
                            :key="cmd.id"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-2 py-3">
                                <Checkbox
                                    :checked="selectedIds.has(cmd.id)"
                                    @update:checked="() => toggleOne(cmd.id)"
                                />
                            </td>
                            <td class="px-4 py-3 font-mono">
                                {{ cmd.numero }}
                            </td>
                            <td class="px-4 py-3">
                                {{ cmd.client?.prenom }} {{ cmd.client?.nom }} -
                                {{ cmd.client?.tel }}
                            </td>
                            <td class="px-4 py-3">
                                {{ cmd.pharmacie?.designation }}
                            </td>
                            <td class="px-4 py-3">{{ cmd.date }}</td>
                            <td class="px-4 py-3">
                                {{
                                    Number(cmd.prix_total).toLocaleString(
                                        'fr-FR',
                                    )
                                }}
                                XAF
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="{
                                        'bg-blue-100 dark:bg-blue-900/30':
                                            cmd.status === 'nouvelle',
                                        'bg-emerald-100 dark:bg-emerald-900/30':
                                            cmd.status === 'validee' ||
                                            cmd.status === 'livree',
                                    }"
                                >
                                    {{ cmd.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/commandes/${cmd.id}`"
                                    class="text-primary hover:underline"
                                    >Détails</Link
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
