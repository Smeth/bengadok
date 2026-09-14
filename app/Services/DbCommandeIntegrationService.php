<?php

namespace App\Services;

use App\Models\Commande;
use App\Support\CommandeInitialStatusOverrides;
use App\Models\DbCommande;
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
            $commande = DB::transaction(function () use ($dbCommande) {
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

                $this->applyHistoricalAmounts($commande, $dbCommande);

                $dbCommande->update([
                    'commande_id' => $commande->id,
                    'integrated_at' => now(),
                    'integration_error' => null,
                ]);

                return $commande->fresh();
            });
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

        return compact('integrated', 'failed', 'errors');
    }

    /**
     * @return array{integrated: int, failed: int, errors: list<string>}
     */
    public function integrateAllPending(): array
    {
        $ids = DbCommande::query()
            ->whereNull('commande_id')
            ->orderBy('date_commande')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return $this->integrateBulk($ids);
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

    private function applyHistoricalAmounts(Commande $commande, DbCommande $dbCommande): void
    {
        $prixMed = $dbCommande->ca_medicaments ?? $dbCommande->montant_produits;
        $prixPara = $dbCommande->ca_parapharmacie ?? 0;
        $livraison = (float) ($dbCommande->frais_livraison ?? 0);
        $total = $dbCommande->total_paye_client;

        $commande->update([
            'prix_medicaments' => $prixMed ?? $commande->prix_medicaments,
            'prix_parapharma' => $prixPara ?? $commande->prix_parapharma,
            'prix_total' => $total ?? ((float) $prixMed + (float) $prixPara + $livraison),
        ]);
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
