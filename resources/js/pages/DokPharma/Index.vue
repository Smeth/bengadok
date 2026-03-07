<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, FileQuestion, Package } from 'lucide-vue-next';
import { usePolling } from '@/composables/usePolling';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

usePolling();

const props = defineProps<{
    commandes: {
        data: Array<{
            id: number;
            numero: string;
            date: string;
            status: string;
            client: { nom: string; prenom: string };
            produits: Array<{
                id: number;
                designation: string;
                pivot: { quantite: number; prix_unitaire: number; status: string };
            }>;
            ordonnance_id?: number;
        }>;
    };
    stats: { nouvelles: number; a_preparer: number; retirees: number; livrees: number };
    onglet: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Dok Pharma', href: '/dok-pharma' },
];

function changeOnglet(o: string) {
    router.get('/dok-pharma', { onglet: o }, { preserveState: true });
}
</script>

<template>
    <Head title="Dok Pharma - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <h1 class="text-2xl font-semibold">Dok Pharma</h1>

            <div class="flex flex-wrap gap-2">
                <Button
                    :variant="onglet === 'nouvelles' ? 'default' : 'outline'"
                    @click="changeOnglet('nouvelles')"
                >
                    {{ stats.nouvelles }} Nouvelles Commandes
                </Button>
                <Button
                    :variant="onglet === 'a_preparer' ? 'default' : 'outline'"
                    @click="changeOnglet('a_preparer')"
                >
                    {{ stats.a_preparer }} À préparer
                </Button>
                <Button
                    :variant="onglet === 'retirees' ? 'default' : 'outline'"
                    @click="changeOnglet('retirees')"
                >
                    {{ stats.retirees }} Retirées
                </Button>
                <Button
                    :variant="onglet === 'livrees' ? 'default' : 'outline'"
                    @click="changeOnglet('livrees')"
                >
                    {{ stats.livrees }} Livrées
                </Button>
            </div>

            <div class="space-y-4">
                <div
                    v-for="cmd in commandes.data"
                    :key="cmd.id"
                    class="rounded-xl border p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="font-mono font-semibold">{{ cmd.numero }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ cmd.produits?.length ?? 0 }} Médicaments demandés
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ cmd.date }} {{ cmd.client?.prenom }} {{ cmd.client?.nom }}
                            </p>
                        </div>
                        <Link :href="`/commandes/${cmd.id}`">
                            <Button>Voir / Valider</Button>
                        </Link>
                    </div>
                    <div class="mt-4 space-y-2 border-t pt-4">
                        <div
                            v-for="p in cmd.produits"
                            :key="p.id"
                            class="flex items-center justify-between rounded bg-muted/50 px-3 py-2"
                        >
                            <span>{{ p.designation }}</span>
                            <span>{{ p.pivot.quantite }} × {{ Number(p.pivot.prix_unitaire).toLocaleString('fr-FR') }} XAF</span>
                        </div>
                    </div>
                </div>

                <p v-if="!commandes.data?.length" class="py-8 text-center text-muted-foreground">
                    Aucune commande dans cette catégorie.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
