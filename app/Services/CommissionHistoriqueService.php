<?php

namespace App\Services;

use App\Models\CommissionPeriode;
use App\Models\Pharmacie;

/**
 * Recalcule et synchronise l'historique des commissions parapharma en base
 * à partir du CA réel (commandes / lignes parapharmacie).
 */
final class CommissionHistoriqueService
{
    public function __construct(
        private AdminParapharmaDashboardService $parapharmaDashboard,
    ) {}

    /**
     * @return array{ca: float, montant: int}
     */
    public function caEtCommissionPourMois(int $annee, int $mois, ?int $pharmacieId): array
    {
        return $this->parapharmaDashboard->caParapharmaEtCommissionPourMois($annee, $mois, $pharmacieId);
    }

    /**
     * Synchronise une période : montant recalculé depuis le CA ; statut payé conservé sauf reset explicite.
     */
    public function synchroniserPeriode(
        int $annee,
        int $mois,
        ?int $pharmacieId,
        bool $creerSiCaNul = false,
    ): ?CommissionPeriode {
        ['ca' => $ca, 'montant' => $montant] = $this->caEtCommissionPourMois($annee, $mois, $pharmacieId);
        $periode = $this->findPeriode($annee, $mois, $pharmacieId);

        if (! $periode && $ca <= 0 && ! $creerSiCaNul) {
            return null;
        }

        if (! $periode) {
            return CommissionPeriode::query()->create([
                'pharmacie_id' => $pharmacieId,
                'annee' => $annee,
                'mois' => $mois,
                'montant' => $montant,
                'statut' => CommissionPeriode::STATUT_EN_COURS,
                'paye_le' => null,
            ]);
        }

        if ($periode->statut === CommissionPeriode::STATUT_PAYE) {
            return $periode;
        }

        $periode->update(['montant' => $montant]);

        return $periode->fresh();
    }

    /**
     * Reconstruit l'historique en base à partir du CA réel.
     *
     * @return array{created: int, updated: int, reset: int, deleted: int, skipped: int}
     */
    public function reconstruireHistorique(
        int $moisEnArriere = 24,
        bool $reinitialiserStatutsPayes = true,
        bool $dryRun = false,
    ): array {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'reset' => 0,
            'deleted' => 0,
            'skipped' => 0,
        ];

        $pharmacieIds = [null];
        foreach (Pharmacie::query()->orderBy('id')->pluck('id') as $id) {
            $pharmacieIds[] = (int) $id;
        }

        for ($i = 0; $i <= $moisEnArriere; $i++) {
            $ref = now()->copy()->subMonths($i)->startOfMonth();

            foreach ($pharmacieIds as $pharmacieId) {
                $result = $this->synchroniserScopeMois(
                    $ref->year,
                    $ref->month,
                    $pharmacieId,
                    $reinitialiserStatutsPayes,
                    $dryRun,
                );

                foreach ($result as $key => $count) {
                    $stats[$key] += $count;
                }
            }
        }

        return $stats;
    }

    /**
     * @return array{created: int, updated: int, reset: int, deleted: int, skipped: int}
     */
    private function synchroniserScopeMois(
        int $annee,
        int $mois,
        ?int $pharmacieId,
        bool $reinitialiserStatutsPayes,
        bool $dryRun,
    ): array {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'reset' => 0,
            'deleted' => 0,
            'skipped' => 0,
        ];

        ['ca' => $ca, 'montant' => $montant] = $this->caEtCommissionPourMois($annee, $mois, $pharmacieId);
        $periode = $this->findPeriode($annee, $mois, $pharmacieId);

        if ($ca <= 0 && ! $periode) {
            $stats['skipped']++;

            return $stats;
        }

        if ($ca <= 0 && $periode) {
            if ($dryRun) {
                $stats['deleted']++;

                return $stats;
            }

            $periode->delete();
            $stats['deleted']++;

            return $stats;
        }

        if ($reinitialiserStatutsPayes && $periode?->statut === CommissionPeriode::STATUT_PAYE) {
            if ($dryRun) {
                $stats['reset']++;
            } else {
                $periode->update([
                    'statut' => CommissionPeriode::STATUT_EN_COURS,
                    'paye_le' => null,
                    'montant' => $montant,
                ]);
                $periode = $periode->fresh();
                $stats['reset']++;
            }
        } elseif ($periode?->statut === CommissionPeriode::STATUT_PAYE) {
            $stats['skipped']++;

            return $stats;
        }

        if (! $periode) {
            if ($dryRun) {
                $stats['created']++;

                return $stats;
            }

            CommissionPeriode::query()->create([
                'pharmacie_id' => $pharmacieId,
                'annee' => $annee,
                'mois' => $mois,
                'montant' => $montant,
                'statut' => CommissionPeriode::STATUT_EN_COURS,
                'paye_le' => null,
            ]);
            $stats['created']++;

            return $stats;
        }

        if ($dryRun) {
            if ((int) round((float) $periode->montant) !== $montant) {
                $stats['updated']++;
            } else {
                $stats['skipped']++;
            }

            return $stats;
        }

        if ((int) round((float) $periode->montant) !== $montant) {
            $periode->update(['montant' => $montant]);
            $stats['updated']++;
        } else {
            $stats['skipped']++;
        }

        return $stats;
    }

    private function findPeriode(int $annee, int $mois, ?int $pharmacieId): ?CommissionPeriode
    {
        $query = CommissionPeriode::query()
            ->where('annee', $annee)
            ->where('mois', $mois);

        if ($pharmacieId !== null) {
            $query->where('pharmacie_id', $pharmacieId);
        } else {
            $query->whereNull('pharmacie_id');
        }

        return $query->first();
    }
}
