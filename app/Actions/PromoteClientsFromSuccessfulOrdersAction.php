<?php

namespace App\Actions;

use App\Models\Client;
use App\Models\Commande;

/**
 * Distingue prospects (promu_client_le null) vs clients définitifs.
 * Promotion au retrait pharmacie confirmé (status_pharmacie = livre) — même règle
 * que le CA et les ventes comptabilisées.
 */
final class PromoteClientsFromSuccessfulOrdersAction
{
    /** @internal Appel après confirmation du retrait côté pharmacie (DokPharma). */
    public static function afterPharmacieRetrait(Commande $commande): void
    {
        $commande->refresh();

        if ($commande->status_pharmacie !== Commande::STATUT_PHARMACIE_CA_COMPTABILISE) {
            return;
        }

        self::promoteClientIfProspect($commande);
    }

    /**
     * Repli si l'admin marque « Livrée » sans promotion antérieure (données historiques).
     *
     * @internal Appel après mise à jour en base du statut admin.
     */
    public static function afterAdmin(Commande $commande, string $nouveauStatutAdmin): void
    {
        if ($nouveauStatutAdmin !== 'retiree') {
            return;
        }

        $commande->refresh();

        if ($commande->status_pharmacie !== Commande::STATUT_PHARMACIE_CA_COMPTABILISE) {
            return;
        }

        self::promoteClientIfProspect($commande);
    }

    private static function promoteClientIfProspect(Commande $commande): void
    {
        if ($commande->client_id === null) {
            return;
        }

        Client::query()
            ->where('id', $commande->client_id)
            ->whereNull('promu_client_le')
            ->update(['promu_client_le' => now()]);
    }
}
