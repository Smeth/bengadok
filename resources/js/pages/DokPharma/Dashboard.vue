<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ParapharmaDashboardPanel from '@/components/ParapharmaDashboardPanel.vue';
import PharmacyLayout from '@/layouts/PharmacyLayout.vue';

type MoisOption = { value: string; label: string };
type VenteLigne = {
    date: string;
    produit: string;
    categorie: string;
    montant: number;
    commande_eligible_credit: boolean;
    credit_utilise: number;
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
    commande_eligible_credit: boolean;
    credit_utilise: boolean;
};

withDefaults(
    defineProps<{
        pharmacie_id?: number | null;
        pharmacie?: {
            id: number;
            designation: string;
            telephone?: string;
            email?: string | null;
        } | null;
        pharmacies_disponibles?: Array<{ id: number; designation: string }>;
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
        pharmacie_id: null,
        pharmacie: null,
        pharmacies_disponibles: () => [],
    },
);
</script>

<template>
    <Head title="Dashboard Pharmacie - BengaDok" />

    <PharmacyLayout
        page-title="Dashboard Pharmacie"
        variant="plain"
        :pharmacies-disponibles="pharmacies_disponibles"
        :pharmacie-id="pharmacie_id ?? undefined"
        :pharmacie-designation="pharmacie?.designation"
    >
        <div class="px-5 pb-10 sm:px-8">
            <ParapharmaDashboardPanel
                context="pharmacie"
                :mois="mois"
                :mois_label="mois_label"
                :mois_options="mois_options"
                :config="config"
                :kpis="kpis"
                :commission_courante="commission_courante"
                :ventes="ventes"
                :historique_commissions="historique_commissions"
                :commandes_recentes="commandes_recentes"
                :pharmacie_id="pharmacie_id"
                :pharmacie="pharmacie"
            />
        </div>
    </PharmacyLayout>
</template>
