<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

const props = defineProps<{
    commande: {
        id: number;
        numero: string;
        date: string;
        heurs: string;
        status: string;
        prix_total: number;
        commentaire?: string;
        client: { nom: string; prenom: string; tel: string; adresse?: string };
        pharmacie: { designation: string; telephone: string; adresse: string };
        produits: Array<{
            id: number;
            designation: string;
            dosage?: string;
            pivot: { quantite: number; prix_unitaire: number; status: string };
        }>;
        mode_paiement?: { designation: string };
        livreur?: { nom: string; prenom: string; tel: string };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dok Board', href: dashboard() },
    { title: 'Commandes', href: '/commandes' },
    { title: '#' + props.commande.numero, href: '#' },
];

function updateStatus(status: string) {
    router.patch(`/commandes/${props.commande.id}/status`, { status });
}
</script>

<template>
    <Head :title="`Commande ${commande.numero} - BengaDok`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Commande {{ commande.numero }}</h1>
                <div class="flex gap-2">
                    <select
                        :value="commande.status"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        @change="updateStatus(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="nouvelle">Nouvelle</option>
                        <option value="en_attente">En attente</option>
                        <option value="validee">Validée</option>
                        <option value="a_preparer">À préparer</option>
                        <option value="livree">Livrée</option>
                        <option value="annulee">Annulée</option>
                    </select>
                    <Link href="/commandes">
                        <Button variant="outline">Retour</Button>
                    </Link>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border p-4">
                    <h3 class="mb-3 font-semibold">Client</h3>
                    <p>{{ commande.client?.prenom }} {{ commande.client?.nom }}</p>
                    <p class="text-muted-foreground">{{ commande.client?.tel }}</p>
                    <p class="text-muted-foreground">{{ commande.client?.adresse }}</p>
                </div>
                <div class="rounded-xl border p-4">
                    <h3 class="mb-3 font-semibold">Pharmacie</h3>
                    <p>{{ commande.pharmacie?.designation }}</p>
                    <p class="text-muted-foreground">{{ commande.pharmacie?.telephone }}</p>
                    <p class="text-muted-foreground">{{ commande.pharmacie?.adresse }}</p>
                </div>
            </div>

            <div class="rounded-xl border p-4">
                <h3 class="mb-3 font-semibold">Médicaments</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="py-2 text-left">Produit</th>
                            <th class="py-2 text-left">Qté</th>
                            <th class="py-2 text-left">Prix unit.</th>
                            <th class="py-2 text-left">Total</th>
                            <th class="py-2 text-left">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in commande.produits"
                            :key="p.id"
                            class="border-b last:border-0"
                        >
                            <td class="py-2">{{ p.designation }} {{ p.dosage ?? '' }}</td>
                            <td class="py-2">{{ p.pivot.quantite }}</td>
                            <td class="py-2">{{ Number(p.pivot.prix_unitaire).toLocaleString('fr-FR') }} XAF</td>
                            <td class="py-2">
                                {{ (p.pivot.quantite * Number(p.pivot.prix_unitaire)).toLocaleString('fr-FR') }} XAF
                            </td>
                            <td class="py-2">{{ p.pivot.status }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-3 font-semibold">
                    Total : {{ Number(commande.prix_total).toLocaleString('fr-FR') }} XAF
                </p>
            </div>
        </div>
    </AppLayout>
</template>
