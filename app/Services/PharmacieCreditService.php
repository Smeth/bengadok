<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Commande;
use App\Models\Pharmacie;
use App\Models\PharmacieCreditOperation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PharmacieCreditService
{
    public const MODES_PAIEMENT = [
        'solde_interne' => 'Solde interne (BengaDok)',
        'especes' => 'Espèces',
        'virement' => 'Virement bancaire',
        'mobile_money' => 'Mobile Money',
    ];

    /**
     * @return array<string, mixed>
     */
    public function buildGestionPayload(Pharmacie $pharmacie): array
    {
        $cfg = AppSetting::parapharmaConfig();
        $prixUnitaire = $cfg['credit_prix_unitaire_xaf'];
        $seuil = $cfg['credit_seuil_medicament_xaf'];
        $alerteSeuil = $pharmacie->credits_alerte_seuil ?? $cfg['credit_alerte_seuil'];

        [$debutPeriode, $finPeriode] = AppSetting::parapharmaPeriodeBounds();

        $creditsUtilisesPeriode = $this->nbDeductionsPeriode($pharmacie->id, $debutPeriode, $finPeriode, $seuil);
        $coutPeriode = $creditsUtilisesPeriode * $prixUnitaire;
        $solde = (int) $pharmacie->credits_solde;

        $historique = PharmacieCreditOperation::query()
            ->where('pharmacie_id', $pharmacie->id)
            ->with(['user:id,name', 'commande:id,numero'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn (PharmacieCreditOperation $op) => $this->formatOperation($op));

        return [
            'resume' => [
                'credits_disponibles' => $solde,
                'valeur_disponible_xaf' => $solde * $prixUnitaire,
                'credits_utilises_mois' => $creditsUtilisesPeriode,
                'cout_mois_xaf' => $coutPeriode,
                'statut' => $solde <= $alerteSeuil ? 'faible' : 'actif',
                'statut_label' => $solde <= $alerteSeuil ? 'Crédits faibles' : 'Actif',
                'statut_detail' => $solde <= $alerteSeuil ? 'À surveiller' : 'À jour',
            ],
            'config' => [
                'prix_unitaire_xaf' => $prixUnitaire,
                'seuil_medicament_xaf' => $seuil,
                'minimum_achat' => $cfg['credit_minimum_achat'],
                'deduction_auto' => $cfg['credit_deduction_auto'],
                'alerte_seuil' => $alerteSeuil,
                'alerte_seuil_pharmacie' => $pharmacie->credits_alerte_seuil,
            ],
            'modes_paiement' => collect(self::MODES_PAIEMENT)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'historique' => $historique,
            'note_interne' => $pharmacie->note_interne,
            'note_modifie_le' => $pharmacie->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public function recharger(
        Pharmacie $pharmacie,
        int $nombreCredits,
        string $modePaiement,
        ?string $note,
        ?User $user,
    ): PharmacieCreditOperation {
        $cfg = AppSetting::parapharmaConfig();
        $minimum = $cfg['credit_minimum_achat'];
        $prixUnitaire = $cfg['credit_prix_unitaire_xaf'];

        if ($nombreCredits < $minimum) {
            throw new \InvalidArgumentException("L'achat minimum est de {$minimum} crédits.");
        }

        if (! array_key_exists($modePaiement, self::MODES_PAIEMENT)) {
            throw new \InvalidArgumentException('Mode de paiement invalide.');
        }

        $coutTotal = $nombreCredits * $prixUnitaire;

        return DB::transaction(function () use (
            $pharmacie,
            $nombreCredits,
            $modePaiement,
            $note,
            $user,
            $coutTotal,
            $prixUnitaire
        ) {
            $locked = Pharmacie::query()->whereKey($pharmacie->id)->lockForUpdate()->firstOrFail();
            $nouveauSolde = (int) $locked->credits_solde + $nombreCredits;
            $locked->update(['credits_solde' => $nouveauSolde]);

            return PharmacieCreditOperation::query()->create([
                'pharmacie_id' => $locked->id,
                'user_id' => $user?->id,
                'type' => PharmacieCreditOperation::TYPE_RECHARGE,
                'credits_delta' => $nombreCredits,
                'cout_xaf' => $coutTotal,
                'solde_apres' => $nouveauSolde,
                'mode_paiement' => $modePaiement,
                'description' => 'Recharge manuelle de crédits',
                'note' => $note,
            ]);
        });
    }

    /**
     * Déduit 1 crédit si la pharmacie a un solde suffisant (appel métier commande livrée).
     */
    public function deduirePourCommande(Commande $commande): ?PharmacieCreditOperation
    {
        $cfg = AppSetting::parapharmaConfig();
        if (! $cfg['credit_deduction_auto']) {
            return null;
        }

        if (! $commande->pharmacie_id) {
            return null;
        }

        if (! in_array($commande->status, Commande::STATUTS_REUSSIS, true)) {
            return null;
        }

        if ((float) $commande->prix_medicaments < $cfg['credit_seuil_medicament_xaf']) {
            return null;
        }

        $deja = PharmacieCreditOperation::query()
            ->where('commande_id', $commande->id)
            ->where('type', PharmacieCreditOperation::TYPE_DEDUCTION)
            ->exists();

        if ($deja) {
            return null;
        }

        return DB::transaction(function () use ($commande, $cfg) {
            $pharmacie = Pharmacie::query()->whereKey($commande->pharmacie_id)->lockForUpdate()->first();
            if (! $pharmacie || (int) $pharmacie->credits_solde < 1) {
                return null;
            }

            $nouveauSolde = (int) $pharmacie->credits_solde - 1;
            $pharmacie->update(['credits_solde' => $nouveauSolde]);

            $numero = $commande->numero ?? '#CMD'.str_pad((string) $commande->id, 5, '0', STR_PAD_LEFT);

            return PharmacieCreditOperation::query()->create([
                'pharmacie_id' => $pharmacie->id,
                'user_id' => null,
                'commande_id' => $commande->id,
                'type' => PharmacieCreditOperation::TYPE_DEDUCTION,
                'credits_delta' => -1,
                'cout_xaf' => $cfg['credit_prix_unitaire_xaf'],
                'solde_apres' => $nouveauSolde,
                'mode_paiement' => null,
                'description' => "Commande {$numero}",
                'note' => null,
            ]);
        });
    }

    /**
     * Synthèse crédits pour la liste du module pharmacies (onglet « Gestion de crédit »).
     *
     * @return list<array{
     *     id: int,
     *     designation: string,
     *     zone: string|null,
     *     adresse: string,
     *     credits_solde: int,
     *     credits_utilises_mois: int,
     *     cout_mois_xaf: int,
     *     statut: string,
     *     statut_label: string
     * }>
     */
    public function buildIndexCreditsOverview(): array
    {
        $cfg = AppSetting::parapharmaConfig();
        $prixUnitaire = $cfg['credit_prix_unitaire_xaf'];
        $seuil = $cfg['credit_seuil_medicament_xaf'];
        [$debutPeriode, $finPeriode] = AppSetting::parapharmaPeriodeBounds();

        return Pharmacie::query()
            ->with('zone:id,designation')
            ->orderBy('designation')
            ->get()
            ->map(function (Pharmacie $pharmacie) use ($cfg, $prixUnitaire, $seuil, $debutPeriode, $finPeriode) {
                $utilises = $this->nbDeductionsPeriode($pharmacie->id, $debutPeriode, $finPeriode, $seuil);
                $solde = (int) $pharmacie->credits_solde;
                $alerte = $pharmacie->credits_alerte_seuil ?? $cfg['credit_alerte_seuil'];

                return [
                    'id' => $pharmacie->id,
                    'designation' => $pharmacie->designation,
                    'zone' => $pharmacie->zone?->designation,
                    'adresse' => $pharmacie->adresse,
                    'credits_solde' => $solde,
                    'credits_utilises_mois' => $utilises,
                    'cout_mois_xaf' => $utilises * $prixUnitaire,
                    'statut' => $solde <= $alerte ? 'faible' : 'actif',
                    'statut_label' => $solde <= $alerte ? 'Crédits faibles' : 'Actif',
                ];
            })
            ->all();
    }

    public function updateNoteInterne(Pharmacie $pharmacie, ?string $note): void
    {
        $pharmacie->update(['note_interne' => $note !== '' ? $note : null]);
    }

    public function updateAlerteSeuil(Pharmacie $pharmacie, ?int $seuil): void
    {
        $pharmacie->update([
            'credits_alerte_seuil' => $seuil !== null && $seuil > 0 ? $seuil : null,
        ]);
    }

    private function nbDeductionsPeriode(
        int $pharmacieId,
        CarbonInterface $debut,
        CarbonInterface $fin,
        int $seuilMedicament,
    ): int {
        $depuisOps = (int) PharmacieCreditOperation::query()
            ->where('pharmacie_id', $pharmacieId)
            ->where('type', PharmacieCreditOperation::TYPE_DEDUCTION)
            ->whereHas('commande', fn ($q) => $q->whereBetween('date', [$debut, $fin]))
            ->sum(DB::raw('ABS(credits_delta)'));

        if ($depuisOps > 0) {
            return $depuisOps;
        }

        return (int) Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereBetween('date', [$debut, $fin])
            ->whereIn('status', Commande::STATUTS_REUSSIS)
            ->where('prix_medicaments', '>=', $seuilMedicament)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOperation(PharmacieCreditOperation $op): array
    {
        $isRecharge = $op->type === PharmacieCreditOperation::TYPE_RECHARGE;
        $modeLabel = $op->mode_paiement
            ? (self::MODES_PAIEMENT[$op->mode_paiement] ?? $op->mode_paiement)
            : null;

        return [
            'id' => $op->id,
            'date' => $op->created_at?->format('d/m/Y H:i'),
            'type' => $op->type,
            'type_label' => $isRecharge ? 'Recharge' : 'Déduction',
            'description' => $op->description,
            'credits_delta' => $op->credits_delta,
            'credits_affichage' => ($op->credits_delta > 0 ? '+' : '').$op->credits_delta,
            'cout_xaf' => $op->cout_xaf,
            'solde_apres' => $op->solde_apres,
            'utilisateur' => $op->user?->name ?? 'Système',
            'mode_paiement_label' => $modeLabel,
            'note' => $op->note,
            'commande_numero' => $op->commande?->numero,
        ];
    }
}
