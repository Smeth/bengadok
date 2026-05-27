<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Commande;
use App\Models\CommissionPeriode;
use App\Models\Pharmacie;
use App\Models\PharmacieCreditOperation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class AdminParapharmaDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(?string $moisParam = null): array
    {
        $cfg = $this->config();
        $ref = $this->resolveMoisReference($moisParam);
        [$debut, $finPeriode] = AppSetting::parapharmaPeriodeBounds($ref);

        $inStatutsReussis = $this->inListSql(Commande::STATUTS_REUSSIS);
        $inStatutsVentes = $this->inListSql(Commande::STATUTS_STATS_VENTES);

        $caParapharma = $this->sommeCaParapharma($debut, $finPeriode, $inStatutsVentes);
        $montantCommission = (int) round($caParapharma * $cfg['commission_percent'] / 100);

        $periode = $this->syncCommissionPeriode($ref, $montantCommission);

        $nbCommandesParapharma = $this->nbCommandesAvecParapharma($debut, $finPeriode, $inStatutsVentes);

        $creditsUtilises = $this->nbDeductionsPeriode($debut, $finPeriode);
        $creditsDisponibles = (int) Pharmacie::query()->sum('credits_solde');
        $creditsPrepayesTotal = $this->totalRecharges();
        $creditsConsommesTotal = $this->totalDeductions();

        $ventesLignes = $this->ventesParapharmaParLigne(
            $debut,
            $finPeriode,
            $inStatutsVentes,
            $cfg['credit_seuil_medicament_xaf']
        );

        $historique = $this->historiqueCommissions($ref, $cfg);

        $commandesRecentes = $this->commandesRecentes(
            $debut,
            $finPeriode,
            $cfg['credit_seuil_medicament_xaf']
        );

        return [
            'mode' => 'parapharma_admin',
            'mois' => $ref->format('Y-m'),
            'mois_label' => $this->formatMoisFrancais($ref),
            'mois_options' => $this->moisSelectOptions($ref),
            'config' => $cfg,
            'kpis' => [
                'nb_commandes' => $nbCommandesParapharma,
                'ca_parapharma' => $caParapharma,
                'credits_disponibles' => $creditsDisponibles,
                'credits_utilises' => $creditsUtilises,
                'credits_prepayes_total' => $creditsPrepayesTotal,
                'credits_consommes_total' => $creditsConsommesTotal,
                'cout_credits_consommes' => $creditsUtilises * $cfg['credit_prix_unitaire_xaf'],
                'commandes_eligibles_credit' => $this->nbCommandesEligiblesCredit(
                    $debut,
                    $finPeriode,
                    $cfg['credit_seuil_medicament_xaf']
                ),
                'montant_commission' => $montantCommission,
            ],
            'commission_courante' => [
                'periode_label' => sprintf(
                    '01 → %02d %s %d',
                    min($cfg['periode_jour_fin'], $ref->daysInMonth),
                    $this->formatMoisFrancais($ref, false),
                    $ref->year
                ),
                'echeance_label' => sprintf(
                    '%02d %s %d',
                    $cfg['commission_jour_echeance'],
                    $this->formatMoisFrancais($ref, false),
                    $ref->year
                ),
                'montant' => $montantCommission,
                'statut' => $periode->statut,
                'statut_label' => $periode->statut === CommissionPeriode::STATUT_PAYE ? 'Payé' : 'En cours',
                'paye_le' => $periode->paye_le?->format('d/m/Y'),
            ],
            'ventes' => $ventesLignes,
            'historique_commissions' => $historique,
            'commandes_recentes' => $commandesRecentes,
        ];
    }

    public function marquerCommissionPayee(int $annee, int $mois): CommissionPeriode
    {
        $periode = CommissionPeriode::query()->firstOrCreate(
            ['annee' => $annee, 'mois' => $mois],
            ['montant' => 0, 'statut' => CommissionPeriode::STATUT_EN_COURS]
        );

        $periode->update([
            'statut' => CommissionPeriode::STATUT_PAYE,
            'paye_le' => now(),
        ]);

        return $periode->fresh();
    }

    /**
     * @return array{
     *     commission_percent: float,
     *     commission_jour_echeance: int,
     *     periode_jour_fin: int,
     *     credit_seuil_medicament_xaf: int,
     *     credit_prix_unitaire_xaf: int,
     *     credit_minimum_achat: int,
     *     produit_types: list<string>
     * }
     */
    public function config(): array
    {
        return AppSetting::parapharmaConfig();
    }

    /**
     * Applique le filtre « produit parapharmacie » sur une requête joignant produits.
     */
    public function scopeProduitParapharma(QueryBuilder|Builder $query, string $produitsAlias = 'produits'): void
    {
        $types = $this->config()['produit_types'];

        if ($types !== []) {
            $query->whereIn("{$produitsAlias}.type", $types);

            return;
        }

        $query->whereRaw("LOWER({$produitsAlias}.type) LIKE ?", ['%parapharm%']);
    }

    /**
     * Commande éligible à la consommation d’un crédit : commande médicaments via BengaDok, montant médicaments ≥ seuil, livrée avec succès.
     */
    public function commandeEligibleCredit(Commande $commande, ?int $seuil = null): bool
    {
        $seuil ??= $this->config()['credit_seuil_medicament_xaf'];

        return in_array($commande->status, Commande::STATUTS_REUSSIS, true)
            && (float) $commande->prix_medicaments >= $seuil;
    }

    private function totalRecharges(): int
    {
        return (int) PharmacieCreditOperation::query()
            ->where('type', PharmacieCreditOperation::TYPE_RECHARGE)
            ->sum('credits_delta');
    }

    private function totalDeductions(): int
    {
        return (int) PharmacieCreditOperation::query()
            ->where('type', PharmacieCreditOperation::TYPE_DEDUCTION)
            ->sum(DB::raw('ABS(credits_delta)'));
    }

    private function nbDeductionsPeriode(CarbonInterface $debut, CarbonInterface $fin): int
    {
        $depuisOps = (int) PharmacieCreditOperation::query()
            ->where('type', PharmacieCreditOperation::TYPE_DEDUCTION)
            ->whereHas('commande', fn ($q) => $q->whereBetween('date', [$debut, $fin]))
            ->sum(DB::raw('ABS(credits_delta)'));

        if ($depuisOps > 0) {
            return $depuisOps;
        }

        return $this->nbCommandesEligiblesCredit(
            $debut,
            $fin,
            $this->config()['credit_seuil_medicament_xaf']
        );
    }

    private function sommeCaParapharma(
        CarbonInterface $debut,
        CarbonInterface $fin,
        string $inStatuts,
    ): float {
        $query = DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
            ->whereBetween('commandes.date', [$debut, $fin])
            ->whereRaw("commandes.status IN ({$inStatuts})")
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            });

        $this->scopeProduitParapharma($query);

        return (float) $query->selectRaw(
            'COALESCE(SUM(commande_produit.prix_unitaire * COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)), 0) as total'
        )->value('total');
    }

    private function nbCommandesAvecParapharma(
        CarbonInterface $debut,
        CarbonInterface $fin,
        string $inStatuts,
    ): int {
        return (int) DB::table('commandes')
            ->whereBetween('commandes.date', [$debut, $fin])
            ->whereRaw("commandes.status IN ({$inStatuts})")
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('commande_produit')
                    ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
                    ->whereColumn('commande_produit.commande_id', 'commandes.id')
                    ->where(function ($q2) {
                        $q2->whereNull('commande_produit.status')
                            ->orWhere('commande_produit.status', '<>', 'indisponible');
                    });
                $this->scopeProduitParapharma($q);
            })
            ->distinct('commandes.id')
            ->count('commandes.id');
    }

    private function nbCommandesEligiblesCredit(
        CarbonInterface $debut,
        CarbonInterface $fin,
        int $seuil,
    ): int {
        return Commande::query()
            ->whereBetween('date', [$debut, $fin])
            ->whereIn('status', Commande::STATUTS_REUSSIS)
            ->where('prix_medicaments', '>=', $seuil)
            ->count();
    }

    /**
     * @return array<int, array{date: string, produit: string, categorie: string, montant: float, eligible_credit: bool, credit_utilise: int}>
     */
    private function ventesParapharmaParLigne(
        CarbonInterface $debut,
        CarbonInterface $fin,
        string $inStatuts,
        int $seuilCredit,
    ): array {
        $query = DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
            ->whereBetween('commandes.date', [$debut, $fin])
            ->whereRaw("commandes.status IN ({$inStatuts})")
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            });

        $this->scopeProduitParapharma($query);

        $rows = $query
            ->select(
                'commandes.date',
                'commandes.prix_medicaments as commande_montant_medicaments',
                'produits.designation',
                'produits.dosage',
                'produits.forme',
                'produits.type as categorie',
                DB::raw('commande_produit.prix_unitaire * COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite) as ligne_montant')
            )
            ->orderByDesc('commandes.date')
            ->limit(50)
            ->get();

        return $rows->map(function ($r) use ($seuilCredit) {
            $produit = trim($r->designation.' '.($r->dosage ?? '').' '.($r->forme ?? ''));
            $commandeUtiliseCredit = (float) $r->commande_montant_medicaments >= $seuilCredit;

            return [
                'date' => Carbon::parse($r->date)->format('d/m/Y'),
                'produit' => $produit,
                'categorie' => $r->categorie ?: '—',
                'montant' => (float) $r->ligne_montant,
                'commande_utilise_credit' => $commandeUtiliseCredit,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{numero: string, client: string, montant: float, statut: string, statut_slug: string, credit_utilise: bool}>
     */
    private function commandesRecentes(
        CarbonInterface $debut,
        CarbonInterface $fin,
        int $seuilCredit,
    ): array {
        return Commande::query()
            ->whereBetween('date', [$debut, $fin])
            ->with(['client:id,nom,prenom'])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Commande $c) use ($seuilCredit) {
                $montantParapharma = $this->montantParapharmaCommande((int) $c->id);

                return [
                    'numero' => $c->numero ?? ('#CMD'.str_pad((string) $c->id, 5, '0', STR_PAD_LEFT)),
                    'client' => trim(($c->client?->prenom ?? '').' '.($c->client?->nom ?? '')) ?: '—',
                    'montant' => $montantParapharma > 0 ? $montantParapharma : (float) $c->prix_medicaments,
                    'statut' => $this->statutLabel($c->status),
                    'statut_slug' => $c->status,
                    'credit_utilise' => $this->commandeEligibleCredit($c, $seuilCredit),
                ];
            })
            ->values()
            ->all();
    }

    private function montantParapharmaCommande(int $commandeId): float
    {
        $query = DB::table('commande_produit')
            ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
            ->where('commande_produit.commande_id', $commandeId)
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
     * @param  array{commission_percent: float, periode_jour_fin: int}  $cfg
     * @return array<int, array{mois: string, periode: string, montant: int, statut: string, statut_label: string, annee: int, mois_num: int}>
     */
    private function historiqueCommissions(CarbonInterface $refCourante, array $cfg): array
    {
        $inStatutsVentes = $this->inListSql(Commande::STATUTS_STATS_VENTES);
        $items = [];

        for ($i = 1; $i <= 6; $i++) {
            $m = $refCourante->copy()->subMonths($i);
            $debut = $m->copy()->startOfMonth();
            $fin = $m->copy()->day(min($cfg['periode_jour_fin'], $m->daysInMonth))->endOfDay();

            $ca = $this->sommeCaParapharma($debut, $fin, $inStatutsVentes);
            $montantCalcule = (int) round($ca * $cfg['commission_percent'] / 100);

            $periode = CommissionPeriode::query()
                ->where('annee', $m->year)
                ->where('mois', $m->month)
                ->first();

            if (! $periode) {
                $periode = CommissionPeriode::query()->create([
                    'annee' => $m->year,
                    'mois' => $m->month,
                    'montant' => $montantCalcule,
                    'statut' => CommissionPeriode::STATUT_PAYE,
                    'paye_le' => $fin->copy()->addDays(2),
                ]);
            }

            $items[] = [
                'mois' => $this->formatMoisFrancais($m),
                'periode' => sprintf('01-%02d %s', min($cfg['periode_jour_fin'], $m->daysInMonth), $m->format('m/Y')),
                'montant' => (int) round((float) $periode->montant),
                'statut' => $periode->statut,
                'statut_label' => $periode->statut === CommissionPeriode::STATUT_PAYE ? 'Payé' : 'En cours',
                'annee' => $m->year,
                'mois_num' => $m->month,
            ];
        }

        return $items;
    }

    /**
     * @param  list<string>  $values
     */
    private function inListSql(array $values): string
    {
        return collect($values)->map(fn (string $s) => "'".addslashes($s)."'")->implode(',');
    }

    private function resolveMoisReference(?string $moisParam): CarbonInterface
    {
        if (is_string($moisParam) && preg_match('/^\d{4}-\d{2}$/', $moisParam)) {
            return Carbon::createFromFormat('Y-m', $moisParam)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function syncCommissionPeriode(CarbonInterface $ref, int $montant): CommissionPeriode
    {
        $periode = CommissionPeriode::query()->firstOrCreate(
            ['annee' => $ref->year, 'mois' => $ref->month],
            ['montant' => $montant, 'statut' => CommissionPeriode::STATUT_EN_COURS]
        );

        if ($periode->statut !== CommissionPeriode::STATUT_PAYE) {
            $periode->update(['montant' => $montant]);
        }

        return $periode->fresh();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function moisSelectOptions(CarbonInterface $ref): array
    {
        $options = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $ref->copy()->subMonths($i);
            $options[] = [
                'value' => $m->format('Y-m'),
                'label' => $this->formatMoisFrancais($m).' '.$m->year,
            ];
        }

        return $options;
    }

    private function formatMoisFrancais(CarbonInterface $date, bool $avecAnnee = true): string
    {
        $noms = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        $nom = $noms[(int) $date->month] ?? $date->format('F');

        return $avecAnnee ? $nom.' '.$date->year : $nom;
    }

    private function statutLabel(?string $status): string
    {
        return match ($status) {
            'retiree', 'livree' => 'Livré',
            'annulee' => 'Annulée',
            'validee', 'a_preparer' => 'Validée',
            default => 'En cours',
        };
    }
}
