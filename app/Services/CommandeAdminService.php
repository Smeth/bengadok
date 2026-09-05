<?php

namespace App\Services;

use App\Actions\PromoteClientsFromSuccessfulOrdersAction;
use App\Models\Commande;
use App\Models\MontantLivraison;
use App\Models\User;

class CommandeAdminService
{
    public function __construct(
        private CommandeDetailPresenter $detailPresenter,
        private PharmacieCreditService $pharmacieCreditService,
    ) {}

    public function updateComplementaires(Commande $commande, array $validated): void
    {
        $commande->update([
            'commentaire' => isset($validated['commentaire']) && trim((string) $validated['commentaire']) !== ''
                ? trim((string) $validated['commentaire'])
                : null,
        ]);
    }

    /**
     * @return array{count: int}
     */
    public function bulkAnnuler(User $user, array $validated): array
    {
        $query = Commande::whereIn('id', $validated['ids']);
        if ($user->pharmacie_id && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            $query->where('pharmacie_id', $user->pharmacie_id);
        }

        $pharmacieIds = (clone $query)->whereNotIn('status', ['annulee'])->pluck('pharmacie_id')->unique();

        $count = $query->whereNotIn('status', ['annulee'])->update([
            'status' => 'annulee',
            'status_pharmacie' => 'annulee',
            'motif_annulation' => $validated['motif_annulation'],
            'note_annulation' => $validated['note_annulation'] ?? null,
        ]);

        if ($count > 0) {
            BroadcastCommandeNotificationTargets::dispatchForPharmacieIds($pharmacieIds);
        }

        return ['count' => $count];
    }

    /**
     * @return string|null Message d'erreur, ou null si succès
     */
    public function validateStatusTransition(Commande $commande, string $status): ?string
    {
        if ($status === 'validee' && $commande->status_pharmacie === 'indisponible') {
            return 'Aucun médicament n\'est disponible sur cette commande. Vous ne pouvez que l\'annuler (ou un agent peut la renvoyer vers une autre pharmacie).';
        }

        if ($status === 'validee' && $commande->montant_livraison_id === null) {
            return 'Veuillez d\'abord définir le montant de la livraison avant de valider la commande.';
        }

        if ($status === 'validee' && $commande->mode_paiement_id === null) {
            return 'Veuillez d\'abord choisir un mode de paiement avant de valider la commande.';
        }

        if ($status === 'retiree' && $commande->status_pharmacie !== 'livre') {
            return 'La pharmacie doit d\'abord confirmer la remise au livreur avant de marquer la commande comme livrée.';
        }

        if ($status === 'validee' && $commande->parent_id === null) {
            if ($commande->enfants()->where('status', 'nouvelle')->exists()) {
                return 'La 2ème pharmacie n\'a pas encore validé les produits renvoyés.';
            }

            $paiementManquantPourEnfant = $commande->enfants()
                ->where('status', 'en_attente')
                ->where(function ($q) {
                    $q->whereNull('montant_livraison_id')
                        ->orWhereNull('mode_paiement_id');
                })
                ->exists();
            if ($paiementManquantPourEnfant) {
                return 'Définissez le montant de livraison et le mode de paiement sur chaque commande associée — autre pharmacie — encore en attente avant de valider cet ensemble.';
            }
        }

        return null;
    }

    /**
     * @param  array{status: string, motif_annulation?: string|null, note_annulation?: string|null}  $validated
     */
    public function updateStatus(Commande $commande, array $validated): void
    {
        $statusPharmacie = match ($validated['status']) {
            'validee' => 'valide_a_preparer',
            'annulee' => 'annulee',
            default => $commande->status_pharmacie,
        };

        $updatePayload = [
            'status' => $validated['status'],
            'status_pharmacie' => $statusPharmacie,
            'motif_annulation' => $validated['status'] === 'annulee' ? ($validated['motif_annulation'] ?? null) : null,
            'note_annulation' => $validated['status'] === 'annulee' ? ($validated['note_annulation'] ?? null) : null,
        ];

        if ($validated['status'] === 'validee' && $commande->validee_admin_at === null) {
            $updatePayload['validee_admin_at'] = now();
        }

        if ($validated['status'] === 'retiree' && $commande->livree_at === null) {
            $updatePayload['livree_at'] = now();
        }

        $commande->update($updatePayload);
        $commande->refresh();

        if (in_array($validated['status'], Commande::STATUTS_REUSSIS, true)) {
            $this->pharmacieCreditService->deduirePourCommande($commande);
        }

        if ($validated['status'] === 'validee' && $commande->parent_id === null) {
            $pharmacieIdsEnfants = $commande->enfants()
                ->where('status', 'en_attente')
                ->pluck('pharmacie_id')
                ->unique();
            $commande->enfants()->where('status', 'en_attente')->update([
                'status' => 'validee',
                'status_pharmacie' => 'valide_a_preparer',
                'validee_admin_at' => now(),
            ]);
            BroadcastCommandeNotificationTargets::dispatchForPharmacieIds($pharmacieIdsEnfants);
        }

        PromoteClientsFromSuccessfulOrdersAction::afterAdmin($commande, $validated['status']);
    }

    public function statusSuccessMessage(Commande $commande, string $status): string
    {
        $numero = $commande->numero;

        return match ($status) {
            'validee' => "Commande {$numero} validée. La pharmacie peut préparer la commande.",
            'retiree' => "Commande {$numero} marquée comme livrée.",
            'annulee' => "Commande {$numero} annulée.",
            'en_attente' => "Commande {$numero} remise en attente.",
            'nouvelle' => "Commande {$numero} remise au statut nouvelle.",
            default => "Commande {$numero} mise à jour.",
        };
    }

    public function setLivreur(Commande $commande, ?int $livreurId): ?string
    {
        if ($commande->status === 'en_attente' && $commande->status_pharmacie === 'indisponible') {
            return 'Impossible d\'attribuer un livreur tant qu\'aucun médicament n\'est disponible.';
        }

        $commande->update(['livreur_id' => $livreurId]);

        return null;
    }

    public function setAcceptationClient(Commande $commande, bool $acceptation): void
    {
        $commande->update(['acceptation_client' => $acceptation]);
    }

    public function setMontantLivraison(Commande $commande, int $montantLivraisonId): void
    {
        if ($commande->status === 'en_attente' && $commande->status_pharmacie === 'indisponible') {
            throw new \InvalidArgumentException('Impossible de définir les frais de livraison tant qu\'aucun médicament n\'est disponible.');
        }

        $montant = MontantLivraison::findOrFail($montantLivraisonId);
        $montants = CommandeMontantCalculator::fromProduitsRelation($commande->produits);

        $commande->update([
            'montant_livraison_id' => $montantLivraisonId,
            'prix_medicaments' => $montants['prix_medicaments'],
            'prix_parapharma' => $montants['prix_parapharma'],
            'prix_total' => $montants['prix_lignes'] + (float) $montant->designation,
        ]);
    }

    /**
     * @return array{commande: array<string, mixed>}
     */
    public function detailPayloadAfterMontantLivraison(Commande $commande): array
    {
        $commande->load([
            'client', 'pharmacie', 'pharmacieRefusee', 'produits', 'modePaiement',
            'livreur', 'montantLivraison', 'piecesJointes.uploadedBy',
            'enfants.pharmacie', 'enfants.produits', 'enfants.modePaiement',
            'enfants.montantLivraison', 'parent', 'ordonnance',
        ]);

        return ['commande' => $this->detailPresenter->toDetailPayload($commande)];
    }

    public function setModePaiement(Commande $commande, int $modePaiementId): ?string
    {
        if ($commande->status !== 'en_attente') {
            return 'Le mode de paiement ne peut être défini que pour les commandes en attente.';
        }

        if ($commande->status_pharmacie === 'indisponible') {
            return 'Impossible de modifier le mode de paiement tant qu\'aucun médicament n\'est disponible.';
        }

        $commande->update(['mode_paiement_id' => $modePaiementId]);

        return null;
    }
}
