<?php

namespace App\Policies;

use App\Models\Ordonnance;
use App\Models\User;

class OrdonnancePolicy
{
    /**
     * Accès au fichier : l’utilisateur doit pouvoir consulter au moins une commande liée.
     */
    public function viewFile(User $user, Ordonnance $ordonnance): bool
    {
        $commandes = $ordonnance->commandes()->get(['id', 'pharmacie_id']);

        if ($commandes->isEmpty()) {
            return $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center']);
        }

        foreach ($commandes as $commande) {
            if ($user->can('view', $commande)) {
                return true;
            }
        }

        return false;
    }
}
