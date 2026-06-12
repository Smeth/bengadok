<script setup lang="ts">
import ParapharmaDashboardPanel from '@/components/ParapharmaDashboardPanel.vue';

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

defineProps<{
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
}>();
</script>

<template>
    <ParapharmaDashboardPanel
        context="admin"
        v-bind="$props"
    />
</template>
