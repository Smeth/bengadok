<?php

namespace App\Policies;

use App\Models\Commande;
use App\Models\User;

class CommandePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center', 'gerant', 'vendeur']);
    }

    public function view(User $user, Commande $commande): bool
    {
        if ($user->pharmacie_id && !$user->hasAnyRole(['admin', 'super_admin'])) {
            return $commande->pharmacie_id === $user->pharmacie_id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center']);
    }

    public function update(User $user, Commande $commande): bool
    {
        if (!$user->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])) {
            return false;
        }
        if ($user->pharmacie_id && !$user->hasAnyRole(['admin', 'super_admin'])) {
            return $commande->pharmacie_id === $user->pharmacie_id;
        }
        return in_array($commande->status, ['nouvelle', 'en_attente']);
    }

    public function delete(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center']);
    }

    public function bulkAnnuler(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center']);
    }
}
