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
        if ($user->pharmacie_id && ! $user->hasAnyRole(['admin', 'super_admin'])) {
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
        if (! $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])) {
            return false;
        }
        if ($user->pharmacie_id && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            return $commande->pharmacie_id === $user->pharmacie_id;
        }

        return in_array($commande->status, ['nouvelle', 'en_attente']);
    }

    public function delete(User $user, Commande $commande): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center']);
    }

    public function bulkAnnuler(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center']);
    }

    /**
     * Attribuer ou retirer un livreur sur une commande validée ou livrée (back-office).
     */
    public function assignLivreur(User $user, Commande $commande): bool
    {
        if (! $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])) {
            return false;
        }
        if ($user->pharmacie_id && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            return false;
        }

        return in_array($commande->status, ['validee', 'a_preparer', 'retiree'], true);
    }

    /**
     * Changer le statut administratif (valider/livrer/annuler), le montant de livraison
     * ou l'acceptation client — actions déjà réservées au back-office par le middleware
     * de route ; la policy ajoute la même défense en profondeur que sur les autres actions.
     */
    /**
     * Commentaire / bénéficiaire sur commande déjà validée (compléments sans refaire la commande).
     */
    public function updateComplementaires(User $user, Commande $commande): bool
    {
        if (! $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])) {
            return false;
        }

        return $commande->status !== 'annulee';
    }

    public function manageStatut(User $user, Commande $commande): bool
    {
        if (! $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])) {
            return false;
        }
        if ($user->pharmacie_id && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            return $commande->pharmacie_id === $user->pharmacie_id;
        }

        return true;
    }
}
