<?php

namespace App\Actions;

use App\Models\Client;
use App\Models\Commande;

/**
 * Distingue prospects (promu_client_le null) vs clients définitifs : promotion
 * uniquement à la livraison effective (statut admin « livrée », code : retiree).
 * Une commande simplement « validée » peut encore être annulée : on attend donc
 * la livraison — qui rend l'annulation impossible — avant d'enregistrer le client.
 */
final class PromoteClientsFromSuccessfulOrdersAction
{
    /** @internal Appel après mise à jour en base du statut admin. */
    public static function afterAdmin(Commande $commande, string $nouveauStatutAdmin): void
    {
        if ($nouveauStatutAdmin !== 'retiree') {
            return;
        }

        $commande->refresh();

        if ($commande->client_id === null) {
            return;
        }

        Client::query()
            ->where('id', $commande->client_id)
            ->whereNull('promu_client_le')
            ->update(['promu_client_le' => now()]);
    }
}
