<?php

namespace App\Policies;

use App\Models\CommandePieceJointe;
use App\Models\User;

class CommandePieceJointePolicy
{
    public function viewFile(User $user, CommandePieceJointe $pieceJointe): bool
    {
        return $user->can('view', $pieceJointe->commande);
    }

    public function delete(User $user, CommandePieceJointe $pieceJointe): bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])) {
            return $user->can('view', $pieceJointe->commande);
        }

        if (! $user->pharmacie_id || ! $user->hasAnyRole(['gerant', 'vendeur'])) {
            return false;
        }

        return (int) $pieceJointe->commande?->pharmacie_id === (int) $user->pharmacie_id
            && $pieceJointe->commande?->status !== 'annulee';
    }
}
