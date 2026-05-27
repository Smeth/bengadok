<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Commande;
use App\Models\Pharmacie;
use App\Models\PharmacieCreditOperation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PharmacieDashboardService
{
    public function __construct(
        private PharmacieCreditService $creditService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $pharmacieId, string $period, int $chartOffset = 0): array
    {
        $period = in_array($period, ['week', 'month'], true) ? $period : 'month';
        $chartOffset = min(0, max(-52, $chartOffset));

        $bounds = $this->resolveRangeBounds($period, $chartOffset);
        $rangeStart = $bounds['rangeStart'];
        $rangeEndEffective = $bounds['rangeEndEffective'];
        $prevStart = $bounds['prevStart'];
        $prevEnd = $bounds['prevEnd'];

        $cfg = AppSetting::parapharmaConfig();
        [$debutPeriodeCommission, $finPeriodeCommission] = AppSetting::parapharmaPeriodeBounds();

        $statusRevenu = ['livre', 'valide_a_preparer', 'attente_confirmation'];
        $statusTraites = ['attente_confirmation', 'indisponible', 'valide_a_preparer', 'livre'];
        $statusCmd = ['livre', 'valide_a_preparer', 'attente_confirmation', 'nouvelle'];

        $baseRevenu = fn () => Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', $statusRevenu)
            ->whereBetween('created_at', [$rangeStart, $rangeEndEffective]);

        $caMedicaments = (float) (clone $baseRevenu())->sum('prix_medicaments');
        $caParapharma = (float) (clone $baseRevenu())->sum('prix_parapharma');
        $caTotal = $caMedicaments + $caParapharma;

        $caMedicamentsPrec = (float) Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', $statusRevenu)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('prix_medicaments');
        $caParapharmaPrec = (float) Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', $statusRevenu)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('prix_parapharma');
        $caTotalPrec = $caMedicamentsPrec + $caParapharmaPrec;

        $commissionParapharmaPeriode = (int) round(
            $this->sommeCaParapharmaLignes($pharmacieId, $debutPeriodeCommission, $finPeriodeCommission)
            * $cfg['commission_percent'] / 100
        );
        $caParapharmaPeriodeCommission = (int) round(
            $this->sommeCaParapharmaLignes($pharmacieId, $debutPeriodeCommission, $finPeriodeCommission)
        );

        $nbCmdActuelle = Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', $statusCmd)
            ->whereBetween('created_at', [$rangeStart, $rangeEndEffective])
            ->count();

        $nbCmdPrec = Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', $statusCmd)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $nbClientsActuelle = (int) Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereNotNull('client_id')
            ->whereIn('status_pharmacie', $statusCmd)
            ->whereBetween('created_at', [$rangeStart, $rangeEndEffective])
            ->distinct('client_id')
            ->count('client_id');

        $nbClientsPrec = (int) Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereNotNull('client_id')
            ->whereIn('status_pharmacie', $statusCmd)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->distinct('client_id')
            ->count('client_id');

        $nbCommandesTraiteesActuelle = Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', $statusTraites)
            ->whereBetween('created_at', [$rangeStart, $rangeEndEffective])
            ->count();

        $nbCommandesTraiteesPrec = Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', $statusTraites)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $pharmacie = Pharmacie::query()->findOrFail($pharmacieId);
        $creditsDisponibles = (int) $pharmacie->credits_solde;
        $creditsUtilisesPeriode = $this->creditService->nbDeductionsPeriodeForPharmacie(
            $pharmacieId,
            $debutPeriodeCommission,
            $finPeriodeCommission,
            $cfg['credit_seuil_medicament_xaf'],
        );
        $coutCreditsPeriode = $creditsUtilisesPeriode * $cfg['credit_prix_unitaire_xaf'];

        $revenusBruts = Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', $statusRevenu)
            ->whereBetween('created_at', [$rangeStart, $rangeEndEffective])
            ->selectRaw('DATE(created_at) as jour')
            ->selectRaw('SUM(prix_medicaments) as med')
            ->selectRaw('SUM(prix_parapharma) as para')
            ->groupBy('jour')
            ->orderBy('jour')
            ->get()
            ->keyBy('jour');

        $volumeTraites = Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', $statusTraites)
            ->whereBetween('created_at', [$rangeStart, $rangeEndEffective])
            ->selectRaw('DATE(created_at) as jour, COUNT(*) as nb')
            ->groupBy('jour')
            ->get()
            ->keyBy('jour');

        $weekdayLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        $revenusParJour = [];
        $volumeParJour = [];
        $creditsEvolutionParJour = [];

        $opsIndexed = $this->indexCreditOperationsByDay($pharmacieId, $rangeStart, $rangeEndEffective);
        $soldeCourant = $this->soldeCreditsAvant($pharmacieId, $rangeStart);

        for ($d = $rangeStart->copy(); $d->lte($rangeEndEffective); $d = $d->addDay()) {
            $key = $d->format('Y-m-d');
            $label = $period === 'week'
                ? $weekdayLabels[$d->isoWeekday() - 1]
                : $d->format('d');

            $row = $revenusBruts->get($key);
            $jourMed = (float) ($row->med ?? 0);
            $jourPara = (float) ($row->para ?? 0);

            $revenusParJour[] = [
                'label' => $label,
                'valeur' => $jourMed + $jourPara,
                'medicaments' => $jourMed,
                'parapharma' => $jourPara,
            ];

            $volumeParJour[] = [
                'label' => $label,
                'valeur' => (int) ($volumeTraites->get($key)?->nb ?? 0),
            ];

            if ($opsIndexed->has($key)) {
                $soldeCourant = (int) $opsIndexed->get($key);
            }
            $creditsEvolutionParJour[] = [
                'label' => $label,
                'valeur' => $soldeCourant,
            ];
        }

        return [
            'period' => $period,
            'chart_offset' => $chartOffset,
            'config' => [
                'commission_percent' => $cfg['commission_percent'],
                'periode_jour_fin' => $cfg['periode_jour_fin'],
                'commission_jour_echeance' => $cfg['commission_jour_echeance'],
                'credit_prix_unitaire_xaf' => $cfg['credit_prix_unitaire_xaf'],
                'credit_seuil_medicament_xaf' => $cfg['credit_seuil_medicament_xaf'],
            ],
            'commission_periode' => [
                'debut' => $debutPeriodeCommission->format('Y-m-d'),
                'fin' => $finPeriodeCommission->format('Y-m-d'),
                'label' => sprintf(
                    '01 → %02d %s',
                    min($cfg['periode_jour_fin'], $debutPeriodeCommission->daysInMonth),
                    $this->formatMoisFrancais($debutPeriodeCommission, false),
                ),
            ],
            'stats' => [
                'revenu_total' => round($caTotal, 0),
                'ca_medicaments' => round($caMedicaments, 0),
                'ca_parapharma' => round($caParapharma, 0),
                'pct_revenu' => $this->evolutionPct($caTotal, $caTotalPrec),
                'nb_commandes' => $nbCmdActuelle,
                'pct_commandes' => $this->evolutionPct($nbCmdActuelle, $nbCmdPrec),
                'nb_commandes_traitees' => $nbCommandesTraiteesActuelle,
                'pct_commandes_traitees' => $this->evolutionPct($nbCommandesTraiteesActuelle, $nbCommandesTraiteesPrec),
                'montant_commissions' => $commissionParapharmaPeriode,
                'ca_parapharma_periode_commission' => $caParapharmaPeriodeCommission,
                'nb_clients' => $nbClientsActuelle,
                'pct_clients' => $this->evolutionPct($nbClientsActuelle, $nbClientsPrec),
                'credits_disponibles' => $creditsDisponibles,
                'credits_utilises_periode' => $creditsUtilisesPeriode,
                'cout_credits_periode' => $coutCreditsPeriode,
            ],
            'revenusParJour' => $revenusParJour,
            'volumeParJour' => $volumeParJour,
            'creditsEvolutionParJour' => $creditsEvolutionParJour,
            'meilleursVentes' => $this->meilleursVentesMedicaments($pharmacieId, $rangeStart, $rangeEndEffective, $statusRevenu),
            'medicamentsIndisponibles' => $this->medicamentsIndisponibles($pharmacieId, $rangeStart, $rangeEndEffective),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyPayload(string $period, int $chartOffset): array
    {
        $cfg = AppSetting::parapharmaConfig();
        [$debut, $fin] = AppSetting::parapharmaPeriodeBounds();

        return [
            'period' => in_array($period, ['week', 'month'], true) ? $period : 'month',
            'chart_offset' => min(0, max(-52, $chartOffset)),
            'config' => [
                'commission_percent' => $cfg['commission_percent'],
                'periode_jour_fin' => $cfg['periode_jour_fin'],
                'commission_jour_echeance' => $cfg['commission_jour_echeance'],
                'credit_prix_unitaire_xaf' => $cfg['credit_prix_unitaire_xaf'],
                'credit_seuil_medicament_xaf' => $cfg['credit_seuil_medicament_xaf'],
            ],
            'commission_periode' => [
                'debut' => $debut->format('Y-m-d'),
                'fin' => $fin->format('Y-m-d'),
                'label' => sprintf(
                    '01 → %02d %s',
                    min($cfg['periode_jour_fin'], $debut->daysInMonth),
                    $this->formatMoisFrancais($debut, false),
                ),
            ],
            'stats' => [
                'revenu_total' => 0,
                'ca_medicaments' => 0,
                'ca_parapharma' => 0,
                'pct_revenu' => 0,
                'nb_commandes' => 0,
                'pct_commandes' => 0,
                'nb_commandes_traitees' => 0,
                'pct_commandes_traitees' => 0,
                'montant_commissions' => 0,
                'ca_parapharma_periode_commission' => 0,
                'nb_clients' => 0,
                'pct_clients' => 0,
                'credits_disponibles' => 0,
                'credits_utilises_periode' => 0,
                'cout_credits_periode' => 0,
            ],
            'revenusParJour' => [],
            'volumeParJour' => [],
            'creditsEvolutionParJour' => [],
            'meilleursVentes' => [],
            'medicamentsIndisponibles' => [],
        ];
    }

    /**
     * @return array{
     *     rangeStart: CarbonInterface,
     *     rangeEnd: CarbonInterface,
     *     rangeEndEffective: CarbonInterface,
     *     prevStart: CarbonInterface,
     *     prevEnd: CarbonInterface
     * }
     */
    private function resolveRangeBounds(string $period, int $chartOffset): array
    {
        $now = now();

        if ($period === 'week') {
            $rangeStart = $now->copy()->addWeeks($chartOffset)->startOfWeek();
            $rangeEnd = $now->copy()->addWeeks($chartOffset)->endOfWeek();
        } else {
            $rangeStart = $now->copy()->addMonths($chartOffset)->startOfMonth();
            $rangeEnd = $now->copy()->addMonths($chartOffset)->endOfMonth();
        }

        $rangeEndEffective = $rangeEnd->gt($now) ? $now->copy()->endOfDay() : $rangeEnd->copy()->endOfDay();

        if ($period === 'week') {
            $nDays = (int) $rangeStart->diffInDays($rangeEndEffective);
            $prevStart = $rangeStart->copy()->subWeek();
            $prevEnd = $prevStart->copy()->addDays($nDays)->endOfDay();
        } else {
            $dayCount = (int) $rangeStart->diffInDays($rangeEndEffective) + 1;
            $prevStart = $rangeStart->copy()->subMonth()->startOfMonth();
            $prevEnd = $prevStart->copy()->addDays($dayCount - 1)->endOfDay();
            $maxPrev = $rangeStart->copy()->subMonth()->endOfMonth();
            if ($prevEnd->gt($maxPrev)) {
                $prevEnd = $maxPrev;
            }
        }

        return compact('rangeStart', 'rangeEnd', 'rangeEndEffective', 'prevStart', 'prevEnd');
    }

    private function sommeCaParapharmaLignes(
        int $pharmacieId,
        CarbonInterface $debut,
        CarbonInterface $fin,
    ): float {
        $query = DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
            ->where('commandes.pharmacie_id', $pharmacieId)
            ->whereBetween('commandes.created_at', [$debut, $fin])
            ->whereIn('commandes.status_pharmacie', ['livre', 'valide_a_preparer', 'attente_confirmation'])
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            });

        $this->scopeProduitParapharma($query);

        return (float) $query->selectRaw(
            'COALESCE(SUM(commande_produit.prix_unitaire * COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)), 0) as total'
        )->value('total');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function scopeProduitParapharma($query, string $produitsAlias = 'produits'): void
    {
        $types = AppSetting::parapharmaConfig()['produit_types'];

        if ($types !== []) {
            $query->whereIn("{$produitsAlias}.type", $types);

            return;
        }

        $query->whereRaw("LOWER({$produitsAlias}.type) LIKE ?", ['%parapharm%']);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function scopeProduitMedicament($query, string $produitsAlias = 'produits'): void
    {
        $types = AppSetting::parapharmaConfig()['produit_types'];

        if ($types !== []) {
            $query->where(function ($q) use ($types, $produitsAlias) {
                $q->whereNull("{$produitsAlias}.type")
                    ->orWhereNotIn("{$produitsAlias}.type", $types);
            });

            return;
        }

        $query->where(function ($q) use ($produitsAlias) {
            $q->whereNull("{$produitsAlias}.type")
                ->orWhereRaw("LOWER({$produitsAlias}.type) NOT LIKE ?", ['%parapharm%']);
        });
    }

    /**
     * @param  list<string>  $statusRevenu
     * @return list<array{id: int, designation: string, ca: float, quantite: float}>
     */
    private function meilleursVentesMedicaments(
        int $pharmacieId,
        CarbonInterface $debut,
        CarbonInterface $fin,
        array $statusRevenu,
    ): array {
        $query = DB::table('commande_produit')
            ->join('commandes', 'commande_produit.commande_id', '=', 'commandes.id')
            ->join('produits', 'commande_produit.produit_id', '=', 'produits.id')
            ->where('commandes.pharmacie_id', $pharmacieId)
            ->whereIn('commandes.status_pharmacie', $statusRevenu)
            ->whereBetween('commandes.created_at', [$debut, $fin])
            ->whereRaw("(commande_produit.status IS NULL OR commande_produit.status <> 'indisponible')");

        $this->scopeProduitMedicament($query);

        return $query
            ->select(
                'produits.id',
                'produits.designation',
                DB::raw('SUM(COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)) as qte'),
                DB::raw('SUM(commande_produit.prix_unitaire * COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)) as ca')
            )
            ->groupBy('produits.id', 'produits.designation')
            ->orderByDesc('ca')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'designation' => $r->designation,
                'ca' => (float) $r->ca,
                'quantite' => (float) $r->qte,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, designation: string, dosage: string|null, quantite_demandee: int, nb_commandes: int}>
     */
    private function medicamentsIndisponibles(
        int $pharmacieId,
        CarbonInterface $debut,
        CarbonInterface $fin,
    ): array {
        $query = DB::table('commande_produit')
            ->join('commandes', 'commande_produit.commande_id', '=', 'commandes.id')
            ->join('produits', 'commande_produit.produit_id', '=', 'produits.id')
            ->where('commandes.pharmacie_id', $pharmacieId)
            ->where('commande_produit.status', 'indisponible')
            ->whereBetween('commandes.created_at', [$debut, $fin]);

        $this->scopeProduitMedicament($query);

        return $query
            ->select(
                'produits.id',
                'produits.designation',
                'produits.dosage',
                DB::raw('SUM(commande_produit.quantite) as quantite_demandee'),
                DB::raw('COUNT(DISTINCT commandes.id) as nb_commandes')
            )
            ->groupBy('produits.id', 'produits.designation', 'produits.dosage')
            ->orderByDesc('quantite_demandee')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'designation' => $r->designation,
                'dosage' => $r->dosage,
                'quantite_demandee' => (int) $r->quantite_demandee,
                'nb_commandes' => (int) $r->nb_commandes,
            ])
            ->values()
            ->all();
    }

    private function soldeCreditsAvant(int $pharmacieId, CarbonInterface $instant): int
    {
        $last = PharmacieCreditOperation::query()
            ->where('pharmacie_id', $pharmacieId)
            ->where('created_at', '<', $instant)
            ->orderByDesc('created_at')
            ->value('solde_apres');

        return (int) ($last ?? 0);
    }

    /**
     * Dernier solde connu pour chaque jour (clé Y-m-d).
     *
     * @return Collection<string, int>
     */
    private function indexCreditOperationsByDay(
        int $pharmacieId,
        CarbonInterface $debut,
        CarbonInterface $fin,
    ): Collection {
        $ops = PharmacieCreditOperation::query()
            ->where('pharmacie_id', $pharmacieId)
            ->whereBetween('created_at', [$debut, $fin])
            ->orderBy('created_at')
            ->get(['created_at', 'solde_apres']);

        $byDay = [];
        foreach ($ops as $op) {
            $byDay[$op->created_at->format('Y-m-d')] = (int) $op->solde_apres;
        }

        return collect($byDay);
    }

    private function evolutionPct(float|int $current, float|int $previous): int
    {
        if ($previous > 0) {
            return (int) round((($current - $previous) / $previous) * 100);
        }

        return $current > 0 ? 100 : 0;
    }

    private function formatMoisFrancais(CarbonInterface $date, bool $avecAnnee = true): string
    {
        $mois = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];
        $label = $mois[(int) $date->format('n')] ?? $date->format('m');

        return $avecAnnee ? "{$label} {$date->year}" : $label;
    }
}
