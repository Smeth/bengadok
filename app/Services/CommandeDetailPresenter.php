<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\CommandePieceJointe;
use App\Models\User;

class CommandeDetailPresenter
{
    /**
     * @return list<string>
     */
    public function showRelations(User $user): array
    {
        $relations = [
            'client', 'pharmacie', 'pharmacieRefusee', 'produits', 'modePaiement', 'livreur',
            'montantLivraison', 'piecesJointes.uploadedBy', 'enfants.pharmacie', 'enfants.produits',
            'enfants.modePaiement', 'enfants.montantLivraison', 'parent',
        ];
        $relations[] = $this->backofficePeutVoirVerificationOrdonnance($user)
            ? 'ordonnance.verification'
            : 'ordonnance';

        return $relations;
    }

    public function markDejaRelancee(Commande $commande): void
    {
        $commande->setAttribute(
            'deja_relancee',
            $commande->status === 'annulee'
                && Commande::query()->where('relance_de_commande_id', $commande->id)->exists()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(Commande $commande): array
    {
        $payload = $commande->toArray();

        if ($commande->relationLoaded('piecesJointes')) {
            $payload['pieces_jointes'] = $commande->piecesJointes
                ->map(fn (CommandePieceJointe $pj) => $pj->toFrontendArray())
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * OCR / règles métier : réservé au back-office (pas aux comptes pharmacie).
     */
    public function backofficePeutVoirVerificationOrdonnance(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center']);
    }
}
