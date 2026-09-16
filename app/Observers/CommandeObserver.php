<?php

namespace App\Observers;

use App\Models\Commande;
use App\Services\BroadcastCommandeNotificationTargets;
use App\Support\CommandePharmacyBroadcastFields;

class CommandeObserver
{
    public function saved(Commande $commande): void
    {
        if ($commande->wasRecentlyCreated) {
            BroadcastCommandeNotificationTargets::dispatchForCommande($commande);

            return;
        }

        if ($commande->wasChanged(CommandePharmacyBroadcastFields::FIELDS)) {
            BroadcastCommandeNotificationTargets::dispatchForCommande($commande);
        }
    }
}
