<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\DbCommande;
use App\Services\CommandeMontantCalculator;
use App\Support\CommandeInitialStatusOverrides;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DbCommandeIntegrationService
{
    public function __construct(
        private CommandeService $commandeService,
        private CommandeEntityResolverService $resolver,
    ) {}

    public function integrateDbCommande(DbCommande $dbCommande): Commande
    {
        if ($dbCommande->commande_id) {
            throw new RuntimeException('Cette entrée est déjà intégrée au système.');
        }

        try {
            $commande = BroadcastCommandeNotificationTargets::withoutBroadcasting(fn () => DB::transaction(function () use ($dbCommande) {
                $client = $this->resolver->resolveClientFromLegacyRow($dbCommande->toArray());
                $pharmacie = $this->resolver->resolvePharmacie(
                    null,
                    $dbCommande->pharmacie,
                    $dbCommande->arrondissement,
                );

                $produits = $this->resolver->parseProduitLinesFromLegacyRow($dbCommande->toArray());

                $payload = [
                    'client_id' => $client->id,
                    'pharmacie_id' => $pharmacie->id,
                    'produits' => $produits,
                    'mode_paiement_id' => $this->resolver->resolveModePaiementId(null, $dbCommande->mode_paiement),
                    'montant_livraison_id' => $this->resolver->resolveMontantLivraisonId(null, $dbCommande->frais_livraison !== null ? (float) $dbCommande->frais_livraison : null),
                    'livreur_id' => $this->resolver->resolveLivreurId(null, $dbCommande->nom_livreur),
                    'commentaire' => $this->buildLegacyComment($dbCommande),
                    'beneficiaire' => 'Soi-même',
                ];

                $overrides = $this->buildHistoricalOverrides($dbCommande);

                $commande = $this->commandeService->create($payload, null, $overrides);

                $commande = $this->applyHistoricalAmounts($commande, $dbCommande);

                $dbCommande->update([
                    'commande_id' => $commande->id,
                    'integrated_at' => now(),
                    'integration_error' => null,
                ]);

                return $commande->fresh();
            }));
        } catch (\Throwable $e) {
            $dbCommande->update(['integration_error' => $e->getMessage()]);

            throw $e;
        }

        return $commande;
    }

    /**
     * @param  list<int>  $ids
     * @return array{integrated: int, failed: int, errors: list<string>}
     */
    public function integrateBulk(array $ids): array
    {
        $integrated = 0;
        $failed = 0;
        $errors = [];

        $this->resolver->beginBulkClientLookup();

        try {
            foreach ($ids as $id) {
                $row = DbCommande::query()->find($id);
                if (! $row || $row->commande_id) {
                    continue;
                }

                try {
                    $this->integrateDbCommande($row);
                    $integrated++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = sprintf(
                        '%s : %s',
                        $row->code_commande ?? ('#'.$row->id),
                        $e->getMessage(),
                    );
                }
            }
        } finally {
            $this->resolver->endBulkClientLookup();
        }

        return compact('integrated', 'failed', 'errors');
    }

    /**
     * @return array{integrated: int, failed: int, errors: list<string>}
     */
    public function integrateAllPending(): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $ids = DbCommande::query()
            ->whereNull('commande_id')
            ->orderBy('date_commande')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return BroadcastCommandeNotificationTargets::withoutBroadcasting(
            fn () => $this->integrateBulk($ids),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHistoricalOverrides(DbCommande $dbCommande): array
    {
        $statut = DbCommande::resolveStatutSysteme($dbCommande->statut);
        $heurs = $dbCommande->heure_commande
            ? substr((string) $dbCommande->heure_commande, 0, 5)
            : '12:00';

        $overrides = CommandeInitialStatusOverrides::fromStatutAndDate(
            $statut,
            $dbCommande->date_commande,
            $heurs,
            $this->resolveUniqueNumero($dbCommande->code_commande),
        );

        if ($statut === 'retiree') {
            $overrides['livree_at'] = $dbCommande->date_livraison_effective ?? $dbCommande->date_commande;
            $overrides['validee_admin_at'] = $dbCommande->date_commande ?? now();
        }

        return $overrides;
    }

    private function applyHistoricalAmounts(Commande $commande, DbCommande $dbCommande): Commande
    {
        $livraison = (float) ($dbCommande->frais_livraison ?? 0);
        $total = $dbCommande->total_paye_client;

        $this->syncIntegratedProduitPivotAmounts($commande, $dbCommande);
        $this->applyHistoricalProduitPivotStatus($commande, $dbCommande);

        $commande->load('produits');
        $montants = CommandeMontantCalculator::fromProduitsRelation(
            $commande->produits,
            excludeIndisponible: true,
            excludeEnAttente: false,
        );

        $targetMed = (float) ($dbCommande->ca_medicaments ?? $montants['prix_medicaments']);
        $targetPara = (float) ($dbCommande->ca_parapharmacie ?? $montants['prix_parapharma']);
        if ($dbCommande->ca_medicaments === null && $dbCommande->ca_parapharmacie === null && $dbCommande->montant_produits !== null) {
            $targetMed = $montants['prix_medicaments'];
            $targetPara = $montants['prix_parapharma'];
        }

        $commande->update([
            'prix_medicaments' => $targetMed,
            'prix_parapharma' => $targetPara,
            'prix_total' => $total ?? ($targetMed + $targetPara + $livraison),
        ]);

        return $commande->fresh(['produits']);
    }

    private function syncIntegratedProduitPivotAmounts(Commande $commande, DbCommande $dbCommande): void
    {
        $commande->load('produits');
        if ($commande->produits->isEmpty()) {
            return;
        }

        $med = [];
        $para = [];

        foreach ($commande->produits as $produit) {
            if (CommandeMontantCalculator::isParapharmaType($produit->pivot->type ?? $produit->type)) {
                $para[] = $produit;
            } else {
                $med[] = $produit;
            }
        }

        $caMed = (float) ($dbCommande->ca_medicaments ?? 0);
        $caPara = (float) ($dbCommande->ca_parapharmacie ?? 0);
        $montant = (float) ($dbCommande->montant_produits ?? 0);

        if ($caMed > 0 || $caPara > 0) {
            $this->scalePivotGroupToTarget($commande, $med, $caMed);
            $this->scalePivotGroupToTarget($commande, $para, $caPara);

            return;
        }

        if ($montant <= 0) {
            return;
        }

        $medSum = $this->sumPivotLineTotals($med);
        $paraSum = $this->sumPivotLineTotals($para);
        $combined = $medSum + $paraSum;

        if ($combined <= 0) {
            $this->scaleAllPivotsToTotal($commande, $montant);

            return;
        }

        $this->scalePivotGroupToTarget($commande, $med, $montant * ($medSum / $combined));
        $this->scalePivotGroupToTarget($commande, $para, $montant * ($paraSum / $combined));
    }

    private function applyHistoricalProduitPivotStatus(Commande $commande, DbCommande $dbCommande): void
    {
        if (DbCommande::resolveStatutSysteme($dbCommande->statut) !== 'retiree') {
            return;
        }

        $commande->load('produits');

        foreach ($commande->produits as $produit) {
            $status = $produit->pivot->status ?? 'en_attente';
            if ($status === 'indisponible') {
                continue;
            }

            $quantite = max(1, (int) $produit->pivot->quantite);

            $commande->produits()->updateExistingPivot($produit->id, [
                'status' => 'disponible',
                'quantite_confirmee' => $quantite,
            ]);
        }
    }

    /**
     * @param  list<\App\Models\Produit>  $produits
     */
    private function sumPivotLineTotals(array $produits): float
    {
        $sum = 0.0;
        foreach ($produits as $produit) {
            $qte = (int) ($produit->pivot->quantite_confirmee ?? $produit->pivot->quantite);
            $sum += $qte * (float) $produit->pivot->prix_unitaire;
        }

        return $sum;
    }

    private function scaleAllPivotsToTotal(Commande $commande, float $target): void
    {
        $produits = $commande->produits->all();
        $this->scalePivotGroupToTarget($commande, $produits, $target);
    }

    /**
     * @param  list<\App\Models\Produit>  $produits
     */
    private function scalePivotGroupToTarget(Commande $commande, array $produits, float $target): void
    {
        if ($produits === [] || $target < 0) {
            return;
        }

        $current = 0.0;
        foreach ($produits as $produit) {
            $qte = (int) ($produit->pivot->quantite_confirmee ?? $produit->pivot->quantite);
            $current += $qte * (float) $produit->pivot->prix_unitaire;
        }

        if ($target <= 0) {
            foreach ($produits as $produit) {
                $commande->produits()->updateExistingPivot($produit->id, [
                    'prix_unitaire' => 0,
                ]);
            }

            return;
        }

        if ($current <= 0) {
            $weightSum = 0;
            foreach ($produits as $produit) {
                $weightSum += max(1, (int) ($produit->pivot->quantite_confirmee ?? $produit->pivot->quantite));
            }
            $weightSum = max(1, $weightSum);

            foreach ($produits as $produit) {
                $qte = max(1, (int) ($produit->pivot->quantite_confirmee ?? $produit->pivot->quantite));
                $share = $target * $qte / $weightSum;
                $commande->produits()->updateExistingPivot($produit->id, [
                    'prix_unitaire' => round($share / $qte, 2),
                ]);
            }

            return;
        }

        $factor = $target / $current;
        foreach ($produits as $produit) {
            $commande->produits()->updateExistingPivot($produit->id, [
                'prix_unitaire' => round((float) $produit->pivot->prix_unitaire * $factor, 2),
            ]);
        }
    }

    private function buildLegacyComment(DbCommande $dbCommande): ?string
    {
        $parts = array_filter([
            $dbCommande->semaine ? 'Semaine : '.$dbCommande->semaine : null,
            $dbCommande->ligne_index ? 'Ligne import n° '.$dbCommande->ligne_index : null,
            $dbCommande->motif_perte ? 'Motif perte : '.$dbCommande->motif_perte : null,
            $dbCommande->perte ? 'Perte : '.$dbCommande->perte.' FCFA' : null,
            $dbCommande->notes,
            'Import Excel',
        ]);

        return $parts === [] ? null : implode("\n", $parts);
    }

    private function resolveUniqueNumero(?string $code): string
    {
        $base = trim((string) $code);
        if ($base === '') {
            return 'BDK'.now()->format('ymdHis').rand(100, 999);
        }

        if (! Commande::query()->where('numero', $base)->exists()) {
            return $base;
        }

        $suffix = 2;
        while (Commande::query()->where('numero', "{$base}-{$suffix}")->exists()) {
            $suffix++;
        }

        return "{$base}-{$suffix}";
    }
}
