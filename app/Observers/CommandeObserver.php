<?php

namespace App\Observers;

use App\Models\Commande;
use App\Services\BroadcastCommandeNotificationTargets;

class CommandeObserver
{
    public function saved(Commande $commande): void
    {
        $champsNotification = ['status', 'status_pharmacie', 'pharmacie_id'];

        if ($commande->wasRecentlyCreated || $commande->wasChanged($champsNotification)) {
            BroadcastCommandeNotificationTargets::dispatchForCommande($commande);
        }
    }
}
