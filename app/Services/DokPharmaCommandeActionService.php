<?php

namespace App\Services;

use App\Actions\PromoteClientsFromSuccessfulOrdersAction;
use App\Models\Commande;

class DokPharmaCommandeActionService
{
    public function __construct(
        private PharmacieCreditService $pharmacieCreditService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lignes
     * @return string|null Message d'erreur, ou null si succès
     */
    public function validerDisponibilite(Commande $commande, int $pharmacieId, array $lignes, string $commentairePharmacie): ?string
    {
        if (! in_array($commande->status_pharmacie, ['nouvelle', 'attente_confirmation', 'indisponible'], true)) {
            return 'Cette commande a déjà été validée par l\'administrateur et ne peut plus être modifiée.';
        }

        $commande->load('produits');
        $produitMap = $commande->produits->keyBy('id');

        foreach ($lignes as $ligne) {
            $status = $ligne['status'] ?? 'disponible';
            $prixUnitaire = isset($ligne['prix_unitaire']) ? (float) $ligne['prix_unitaire'] : 0;
            if (in_array($status, ['disponible', 'partiel'], true) && $prixUnitaire <= 0) {
                return 'Veuillez saisir le prix pour tous les médicaments disponibles avant d\'envoyer.';
            }
            $qteConfirmeeCheck = isset($ligne['quantite_confirmee']) ? (int) $ligne['quantite_confirmee'] : null;
            if (in_array($status, ['disponible', 'partiel'], true) && $qteConfirmeeCheck !== null && $qteConfirmeeCheck < 1) {
                return 'La quantité confirmée doit être d\'au moins 1 pour un médicament disponible. Marquez-le plutôt indisponible.';
            }
        }

        $nbDispo = 0;
        $nbIndispo = 0;

        foreach ($lignes as $ligne) {
            $produitId = (int) ($ligne['produit_id'] ?? 0);
            $produit = $produitMap->get($produitId);
            if (! $produit) {
                continue;
            }

            $qteDemandee = (int) $produit->pivot->quantite;
            $status = $ligne['status'] ?? 'disponible';
            $prixUnitaire = isset($ligne['prix_unitaire']) ? (float) $ligne['prix_unitaire'] : null;

            $qteConfirmee = isset($ligne['quantite_confirmee']) ? (int) $ligne['quantite_confirmee'] : null;
            if ($qteConfirmee !== null) {
                $qteConfirmee = min($qteConfirmee, $qteDemandee);
            }

            $qteStockee = in_array($status, ['disponible', 'partiel'], true) && $qteConfirmee !== null
                ? $qteConfirmee
                : null;

            $venteLibre = filter_var(
                $ligne['vente_libre'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;

            $typeResolu = CommandeMontantCalculator::isParapharmaType($produit->type)
                ? $produit->type
                : ($venteLibre ? 'Vente libre' : 'Sur ordonnance');

            $pivotData = [
                'status' => $status,
                'quantite_confirmee' => $qteStockee,
                'vente_libre' => $venteLibre,
                'type' => $typeResolu,
            ];
            if ($prixUnitaire !== null) {
                $pivotData['prix_unitaire'] = $prixUnitaire;
            }

            $commande->produits()->updateExistingPivot($produitId, $pivotData);

            if (
                in_array($status, ['disponible', 'partiel'], true)
                && ! CommandeMontantCalculator::isParapharmaType($produit->type)
            ) {
                $produit->update([
                    'type' => $venteLibre ? 'Vente libre' : 'Sur ordonnance',
                ]);
            }

            $qteEffective = $status === 'indisponible' ? 0 : ($qteConfirmee ?? 1);
            if ($qteEffective > 0) {
                $nbDispo++;
            } else {
                $nbIndispo++;
            }
        }

        $commentairePharmacie = trim($commentairePharmacie);

        $commande->load('produits');
        $montants = Commande::computeMontantsFromProduits($commande->produits);
        $commande->load('montantLivraison');
        $liv = (float) ($commande->montantLivraison?->designation ?? 0);

        $commande->update([
            'status' => 'en_attente',
            'status_pharmacie' => $nbDispo === 0 ? 'indisponible' : 'attente_confirmation',
            'pharmacie_refusee_id' => $nbDispo === 0 ? $pharmacieId : null,
            'prix_medicaments' => $montants['prix_medicaments'],
            'prix_parapharma' => $montants['prix_parapharma'],
            'prix_total' => $montants['prix_lignes'] + $liv,
            'dispo_pharmacie_at' => now(),
            ...($commentairePharmacie !== '' ? ['commentaire_pharmacie' => $commentairePharmacie] : []),
        ]);

        return null;
    }

    /**
     * @return string|null Message d'erreur, ou null si succès
     */
    public function validerRetrait(Commande $commande): ?string
    {
        if ($commande->status_pharmacie !== 'valide_a_preparer') {
            return 'Seules les commandes validées peuvent être remises au livreur.';
        }

        $commande->update(['status_pharmacie' => 'livre']);
        $commande->refresh();

        PromoteClientsFromSuccessfulOrdersAction::afterPharmacieRetrait($commande);
        $this->pharmacieCreditService->deduirePourCommande($commande);

        return null;
    }
}
