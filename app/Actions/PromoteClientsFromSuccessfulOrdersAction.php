<?php

namespace App\Actions;

use App\Models\Client;
use App\Models\Commande;

/**
 * Distingue prospects (promu_client_le null) vs clients définitifs : promotion
 * dès statut admin « validée » ou « livrée » (code : retiree, libellé STATUSES).
 */
final class PromoteClientsFromSuccessfulOrdersAction
{
    /** @internal Appel après mise à jour en base du statut admin. */
    public static function afterAdmin(Commande $commande, string $nouveauStatutAdmin): void
    {
        if (! in_array($nouveauStatutAdmin, ['validee', 'retiree'], true)) {
            return;
        }

        $commande->refresh();

        $clientIds = array_filter([
            $commande->client_id,
        ]);

        if ($nouveauStatutAdmin === 'validee' && $commande->parent_id === null) {
            $childIds = Commande::query()
                ->where('parent_id', $commande->id)
                ->where('status', 'validee')
                ->pluck('client_id')
                ->all();
            $clientIds = array_merge($clientIds, $childIds);
        }

        $clientIds = array_values(array_unique(array_filter($clientIds)));

        if ($clientIds === []) {
            return;
        }

        Client::query()
            ->whereIn('id', $clientIds)
            ->whereNull('promu_client_le')
            ->update(['promu_client_le' => now()]);
    }
}
