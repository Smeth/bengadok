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
    private ?int $pharmacieId = null;

    /**
     * @return array<string, mixed>
     */
    public function build(
        ?string $moisParam = null,
        ?int $pharmacieId = null,
        string $vuePeriode = 'mois',
    ): array {
        $this->pharmacieId = $pharmacieId;
        $cfg = $this->config();
        $ref = $this->resolveMoisReference($moisParam);
        [$debutMois, $finMois] = AppSetting::parapharmaPeriodeBounds($ref);
        [$debut, $finPeriode] = $this->resolvePeriodeBounds($ref, $vuePeriode);

        $caParapharma = $this->sommeCaParapharma($debutMois, $finMois);
        $caMedicaments = $this->sommeCaMedicaments($debutMois, $finMois);
        $montantCommission = (int) round($caParapharma * $cfg['commission_percent'] / 100);

        $periode = $this->syncCommissionPeriode($ref, $montantCommission);

        $nbCommandes = $this->nbCommandesTotal($debutMois, $finMois);

        $creditsUtilises = $this->nbDeductionsPeriode($debut, $finPeriode);
        $creditsDisponibles = $this->pharmacieId !== null
            ? (int) (Pharmacie::query()->find($this->pharmacieId)?->credits_solde ?? 0)
            : (int) Pharmacie::query()->sum('credits_solde');
        $creditsPrepayesTotal = $this->totalRecharges();
        $creditsConsommesTotal = $this->totalDeductions();

        $ventesLignes = $this->ventesParLigne(
            $debutMois,
            $finMois,
            $cfg['credit_seuil_medicament_xaf']
        );

        $ventesParPharmacie = $this->pharmacieId === null
            ? $this->ventesParPharmacie($debut, $finPeriode)
            : [];

        $creditsParPharmacie = $this->pharmacieId === null
            ? $this->creditsParPharmacie($debut, $finPeriode, $cfg)
            : [];

        $historique = $this->historiqueCommissions($ref, $cfg);

        $commissionsParPharmacie = $this->pharmacieId === null
            ? $this->commissionsParPharmacie($debutMois, $finMois, $cfg, $ref)
            : [];

        $commandesRecentes = $this->commandesRecentes(
            $debutMois,
            $finMois,
            $cfg['credit_seuil_medicament_xaf']
        );

        $pharmacie = $this->pharmacieId !== null
            ? Pharmacie::query()->find($this->pharmacieId, ['id', 'designation', 'telephone', 'email', 'credits_actif'])
            : null;

        return [
            'mode' => $this->pharmacieId !== null ? 'parapharma_pharmacie' : 'parapharma_admin',
            'pharmacie_id' => $this->pharmacieId,
            'pharmacie' => $pharmacie ? [
                'id' => $pharmacie->id,
                'designation' => $pharmacie->designation,
                'telephone' => $pharmacie->telephone,
                'email' => $pharmacie->email,
                'credits_actif' => (bool) $pharmacie->credits_actif,
            ] : null,
            'mois' => $ref->format('Y-m'),
            'mois_label' => $this->formatMoisFrancais($ref),
            'mois_options' => $this->moisSelectOptions($ref),
            'vue_periode' => in_array($vuePeriode, ['mois', 'semaine'], true) ? $vuePeriode : 'mois',
            'config' => $cfg,
            'kpis' => [
                'nb_commandes' => $nbCommandes,
                'ca_medicaments' => $caMedicaments,
                'ca_parapharma' => $caParapharma,
                'ca_total' => $caMedicaments + $caParapharma,
                'credits_disponibles' => $creditsDisponibles,
                'credits_utilises' => $creditsUtilises,
                'credits_prepayes_total' => $creditsPrepayesTotal,
                'credits_consommes_total' => $creditsConsommesTotal,
                'cout_credits_consommes' => $this->coutDeductionsPeriode($debut, $finPeriode, $cfg['credit_prix_unitaire_xaf']),
                'commandes_eligibles_credit' => $this->nbCommandesEligiblesCredit(
                    $debutMois,
                    $finMois,
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
                'statut_label' => $periode->statut === CommissionPeriode::STATUT_PAYE ? 'Payé' : 'En attente',
                'paye_le' => $periode->paye_le?->format('d/m/Y'),
            ],
            'ventes' => $ventesLignes,
            'ventes_par_pharmacie' => $ventesParPharmacie,
            'credits_par_pharmacie' => $creditsParPharmacie,
            'historique_commissions' => $historique,
            'commissions_par_pharmacie' => $commissionsParPharmacie,
            'commandes_recentes' => $commandesRecentes,
        ];
    }

    public function marquerCommissionPayee(int $annee, int $mois, ?int $pharmacieId = null): CommissionPeriode
    {
        $periode = $this->findCommissionPeriode($annee, $mois, $pharmacieId)
            ?? CommissionPeriode::query()->create([
                'pharmacie_id' => $pharmacieId,
                'annee' => $annee,
                'mois' => $mois,
                'montant' => 0,
                'statut' => CommissionPeriode::STATUT_EN_COURS,
            ]);

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
     * @return array{ca: float, montant: int}
     */
    public function caParapharmaEtCommissionPourMois(int $annee, int $mois, ?int $pharmacieId = null): array
    {
        $pharmacieIdCourant = $this->pharmacieId;
        $this->pharmacieId = $pharmacieId;

        $ref = Carbon::createFromDate($annee, $mois, 1)->startOfMonth();
        $cfg = $this->config();
        [$debut, $fin] = AppSetting::parapharmaPeriodeBounds($ref);
        $ca = $this->sommeCaParapharma($debut, $fin);
        $montant = (int) round($ca * $cfg['commission_percent'] / 100);

        $this->pharmacieId = $pharmacieIdCourant;

        return [
            'ca' => $ca,
            'montant' => $montant,
        ];
    }

    /**
     * Applique le filtre « produit parapharmacie » sur une requête joignant produits
     * (et, si présent, commande_produit). Priorité au type figé sur la ligne de commande
     * (commande_produit.type) sur le type catalogue courant (produits.type) : évite qu'un
     * changement ultérieur du type au catalogue ne reclasse rétroactivement une commande passée.
     */
    public function scopeProduitParapharma(QueryBuilder|Builder $query, string $produitsAlias = 'produits', string $commandeProduitAlias = 'commande_produit'): void
    {
        $types = $this->config()['produit_types'];
        $typeExpr = "COALESCE({$commandeProduitAlias}.type, {$produitsAlias}.type)";

        if ($types !== []) {
            $list = collect($types)->map(fn (string $t) => "'".addslashes($t)."'")->implode(',');
            $query->whereRaw("{$typeExpr} IN ({$list})");

            return;
        }

        $query->whereRaw("LOWER({$typeExpr}) LIKE ?", ['%parapharm%']);
    }

    /**
     * Commande éligible à la consommation d’un crédit : commande médicaments via BengaDok, montant médicaments ≥ seuil, livrée avec succès.
     */
    public function commandeEligibleCredit(Commande $commande, ?int $seuil = null): bool
    {
        $seuil ??= $this->config()['credit_seuil_medicament_xaf'];

        return $commande->status_pharmacie === Commande::STATUT_PHARMACIE_CA_COMPTABILISE
            && (float) $commande->prix_medicaments >= $seuil;
    }

    private function totalRecharges(): int
    {
        $query = PharmacieCreditOperation::query()
            ->where('type', PharmacieCreditOperation::TYPE_RECHARGE);

        if ($this->pharmacieId !== null) {
            $query->where('pharmacie_id', $this->pharmacieId);
        }

        return (int) $query->sum('credits_delta');
    }

    private function totalDeductions(): int
    {
        $query = PharmacieCreditOperation::query()
            ->where('type', PharmacieCreditOperation::TYPE_DEDUCTION);

        if ($this->pharmacieId !== null) {
            $query->where('pharmacie_id', $this->pharmacieId);
        }

        return (int) $query->sum(DB::raw('ABS(credits_delta)'));
    }

    private function nbDeductionsPeriode(CarbonInterface $debut, CarbonInterface $fin): int
    {
        $opsQuery = PharmacieCreditOperation::query()
            ->where('type', PharmacieCreditOperation::TYPE_DEDUCTION)
            ->whereHas('commande', function ($q) use ($debut, $fin) {
                $q->whereBetween('date', [$debut, $fin]);
                if ($this->pharmacieId !== null) {
                    $q->where('pharmacie_id', $this->pharmacieId);
                }
            });

        if ($this->pharmacieId !== null) {
            $opsQuery->where('pharmacie_id', $this->pharmacieId);
        }

        $depuisOps = (int) $opsQuery->sum(DB::raw('ABS(credits_delta)'));

        return $depuisOps;
    }

    private function coutDeductionsPeriode(
        CarbonInterface $debut,
        CarbonInterface $fin,
        int $prixUnitaireFallback,
    ): int {
        $opsQuery = PharmacieCreditOperation::query()
            ->where('type', PharmacieCreditOperation::TYPE_DEDUCTION)
            ->whereHas('commande', function ($q) use ($debut, $fin) {
                $q->whereBetween('date', [$debut, $fin]);
                if ($this->pharmacieId !== null) {
                    $q->where('pharmacie_id', $this->pharmacieId);
                }
            });

        if ($this->pharmacieId !== null) {
            $opsQuery->where('pharmacie_id', $this->pharmacieId);
        }

        $cout = (int) $opsQuery->sum('cout_xaf');
        if ($cout > 0) {
            return $cout;
        }

        return $this->nbDeductionsPeriode($debut, $fin) * $prixUnitaireFallback;
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function resolvePeriodeBounds(CarbonInterface $ref, string $vuePeriode): array
    {
        [$debutMois, $finMois] = AppSetting::parapharmaPeriodeBounds($ref);

        if ($vuePeriode !== 'semaine') {
            return [$debutMois, $finMois];
        }

        $fin = $finMois->copy()->min(now()->endOfDay());
        $debut = $fin->copy()->subDays(6)->startOfDay();
        if ($debut->lt($debutMois)) {
            $debut = $debutMois->copy();
        }

        return [$debut, $fin];
    }

    /**
     * @return array<int, array{
     *     date: string,
     *     pharmacie: string,
     *     ca_medicaments: float,
     *     ca_parapharma: float,
     *     ca_total: float,
     *     nb_commandes: int
     * }>
     */
    private function ventesParPharmacie(
        CarbonInterface $debut,
        CarbonInterface $fin,
    ): array {
        $query = DB::table('commandes')
            ->join('pharmacies', 'pharmacies.id', '=', 'commandes.pharmacie_id')
            ->whereBetween('commandes.date', [$debut, $fin]);

        $rows = $this->whereCaComptabilise($query)
            ->groupBy('commandes.date', 'commandes.pharmacie_id', 'pharmacies.designation')
            ->selectRaw(
                'commandes.date as jour,
                pharmacies.designation as pharmacie,
                COALESCE(SUM(commandes.prix_medicaments), 0) as ca_medicaments,
                COALESCE(SUM(commandes.prix_parapharma), 0) as ca_parapharma,
                COALESCE(SUM(commandes.prix_medicaments + commandes.prix_parapharma), 0) as ca_total,
                COUNT(*) as nb_commandes'
            )
            ->orderByDesc('commandes.date')
            ->orderBy('pharmacies.designation')
            ->limit(80)
            ->get();

        return $rows->map(fn ($r) => [
            'date' => $this->formatDateCourte(Carbon::parse($r->jour)),
            'pharmacie' => (string) $r->pharmacie,
            'ca_medicaments' => (float) $r->ca_medicaments,
            'ca_parapharma' => (float) $r->ca_parapharma,
            'ca_total' => (float) $r->ca_total,
            'nb_commandes' => (int) $r->nb_commandes,
        ])->values()->all();
    }

    /**
     * @param  array{
     *     credit_prix_unitaire_xaf: int,
     *     credit_seuil_medicament_xaf: int
     * }  $cfg
     * @return array<int, array{
     *     pharmacie_id: int,
     *     pharmacie: string,
     *     credits_medicaments: int,
     *     credits_parapharmacie: int,
     *     credits_total: int,
     *     cout_total: int,
     *     commandes_eligibles: int
     * }>
     */
    private function creditsParPharmacie(
        CarbonInterface $debut,
        CarbonInterface $fin,
        array $cfg,
    ): array {
        $prixUnitaire = $cfg['credit_prix_unitaire_xaf'];
        $seuil = $cfg['credit_seuil_medicament_xaf'];
        $items = [];

        foreach (Pharmacie::query()->orderBy('designation')->get(['id', 'designation']) as $pharmacie) {
            $ops = PharmacieCreditOperation::query()
                ->where('pharmacie_id', $pharmacie->id)
                ->where('type', PharmacieCreditOperation::TYPE_DEDUCTION)
                ->whereHas('commande', fn ($q) => $q->whereBetween('date', [$debut, $fin]))
                ->with('commande:id,prix_parapharma')
                ->get();

            $creditsMedicaments = 0;
            $creditsParapharmacie = 0;
            $coutTotal = 0;

            foreach ($ops as $op) {
                $qty = abs((int) $op->credits_delta);
                $coutTotal += (int) ($op->cout_xaf ?: $qty * $prixUnitaire);
                $creditsMedicaments += $qty;
            }

            $commandesEligibles = Commande::query()
                ->where('pharmacie_id', $pharmacie->id)
                ->whereBetween('date', [$debut, $fin])
                ->caComptabilise()
                ->where('prix_medicaments', '>=', $seuil)
                ->count();

            $items[] = [
                'pharmacie_id' => (int) $pharmacie->id,
                'pharmacie' => $pharmacie->designation,
                'credits_medicaments' => $creditsMedicaments,
                'credits_parapharmacie' => $creditsParapharmacie,
                'credits_total' => $creditsMedicaments + $creditsParapharmacie,
                'cout_total' => $coutTotal > 0 ? $coutTotal : ($creditsMedicaments + $creditsParapharmacie) * $prixUnitaire,
                'commandes_eligibles' => $commandesEligibles,
            ];
        }

        return $items;
    }

    private function formatDateCourte(CarbonInterface $date): string
    {
        $mois = [
            1 => 'Jan.', 2 => 'Fév.', 3 => 'Mar.', 4 => 'Avr.', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juil.', 8 => 'Août', 9 => 'Sep.', 10 => 'Oct.', 11 => 'Nov.', 12 => 'Déc.',
        ];

        return sprintf(
            '%02d %s %d',
            $date->day,
            $mois[(int) $date->month] ?? $date->format('M'),
            $date->year,
        );
    }

    /**
     * @return list<int>
     */
    private function commandeIdsAvecDeductionPeriode(
        CarbonInterface $debut,
        CarbonInterface $fin,
    ): array {
        $query = PharmacieCreditOperation::query()
            ->where('type', PharmacieCreditOperation::TYPE_DEDUCTION)
            ->whereNotNull('commande_id')
            ->whereHas('commande', function ($q) use ($debut, $fin) {
                $q->whereBetween('date', [$debut, $fin]);
                if ($this->pharmacieId !== null) {
                    $q->where('pharmacie_id', $this->pharmacieId);
                }
            });

        if ($this->pharmacieId !== null) {
            $query->where('pharmacie_id', $this->pharmacieId);
        }

        return $query->pluck('commande_id')->unique()->values()->all();
    }

    private function commandeCreditDeduit(int $commandeId, array $commandeIdsAvecDeduction): bool
    {
        return in_array($commandeId, $commandeIdsAvecDeduction, true);
    }

    private function sommeCaParapharma(
        CarbonInterface $debut,
        CarbonInterface $fin,
    ): float {
        $query = DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
            ->whereBetween('commandes.date', [$debut, $fin]);
        $this->whereCaComptabilise($query)
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            });

        if ($this->pharmacieId !== null) {
            $query->where('commandes.pharmacie_id', $this->pharmacieId);
        }

        $this->scopeProduitParapharma($query);

        return (float) $query->selectRaw(
            'COALESCE(SUM(commande_produit.prix_unitaire * COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)), 0) as total'
        )->value('total');
    }

    /** Même méthode que sommeCaParapharma, sur les lignes médicaments (non parapharma). */
    private function sommeCaMedicaments(
        CarbonInterface $debut,
        CarbonInterface $fin,
    ): float {
        $query = DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
            ->whereBetween('commandes.date', [$debut, $fin]);
        $this->whereCaComptabilise($query)
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            });

        if ($this->pharmacieId !== null) {
            $query->where('commandes.pharmacie_id', $this->pharmacieId);
        }

        $this->scopeProduitMedicament($query);

        return (float) $query->selectRaw(
            'COALESCE(SUM(commande_produit.prix_unitaire * COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)), 0) as total'
        )->value('total');
    }

    /**
     * Applique le filtre « produit médicament » (inverse de scopeProduitParapharma) sur une
     * requête joignant produits et commande_produit.
     */
    private function scopeProduitMedicament(QueryBuilder|Builder $query, string $produitsAlias = 'produits', string $commandeProduitAlias = 'commande_produit'): void
    {
        $types = $this->config()['produit_types'];
        $typeExpr = "COALESCE({$commandeProduitAlias}.type, {$produitsAlias}.type)";

        if ($types !== []) {
            $list = collect($types)->map(fn (string $t) => "'".addslashes($t)."'")->implode(',');
            $query->whereRaw("({$typeExpr} IS NULL OR {$typeExpr} NOT IN ({$list}))");

            return;
        }

        $query->whereRaw("({$typeExpr} IS NULL OR LOWER({$typeExpr}) NOT LIKE ?)", ['%parapharm%']);
    }

    /**
     * Total des commandes de la période (tous produits confondus). La carte "Commandes"
     * du dashboard n'est pas restreinte à la parapharmacie — contrairement au CA/ventes
     * détaillées ci-dessous — donc on ne filtre pas par type de produit ici.
     */
    private function nbCommandesTotal(
        CarbonInterface $debut,
        CarbonInterface $fin,
    ): int {
        $query = DB::table('commandes')
            ->whereBetween('commandes.date', [$debut, $fin]);
        $this->whereCaComptabilise($query);

        if ($this->pharmacieId !== null) {
            $query->where('commandes.pharmacie_id', $this->pharmacieId);
        }

        return (int) $query->count();
    }

    private function nbCommandesEligiblesCredit(
        CarbonInterface $debut,
        CarbonInterface $fin,
        int $seuil,
    ): int {
        $query = Commande::query()
            ->whereBetween('date', [$debut, $fin])
            ->caComptabilise()
            ->where('prix_medicaments', '>=', $seuil);

        if ($this->pharmacieId !== null) {
            $query->where('pharmacie_id', $this->pharmacieId);
        }

        return $query->count();
    }

    /**
     * Détail des ventes, toutes lignes confondues (médicaments + parapharmacie) : la carte
     * "Commandes" et ce tableau ne sont pas restreints à la parapharmacie, contrairement au
     * CA/commission qui restent scopés parapharma (voir sommeCaParapharma).
     *
     * @return array<int, array{date: string, produit: string, categorie: string, montant: float, commande_eligible_credit: bool, credit_utilise: int}>
     */
    private function ventesParLigne(
        CarbonInterface $debut,
        CarbonInterface $fin,
        int $seuilCredit,
    ): array {
        $commandeIdsAvecDeduction = $this->commandeIdsAvecDeductionPeriode($debut, $fin);

        $query = DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
            ->whereBetween('commandes.date', [$debut, $fin]);
        $this->whereCaComptabilise($query)
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            });

        if ($this->pharmacieId !== null) {
            $query->where('commandes.pharmacie_id', $this->pharmacieId);
        }

        $rows = $query
            ->select(
                'commandes.id as commande_id',
                'commandes.date',
                'commandes.prix_medicaments as commande_montant_medicaments',
                'produits.designation',
                'produits.dosage',
                'produits.forme',
                DB::raw('COALESCE(commande_produit.type, produits.type) as categorie'),
                DB::raw('commande_produit.prix_unitaire * COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite) as ligne_montant')
            )
            ->orderByDesc('commandes.date')
            ->limit(50)
            ->get();

        return $rows->map(function ($r) use ($seuilCredit, $commandeIdsAvecDeduction) {
            $produit = trim($r->designation.' '.($r->dosage ?? '').' '.($r->forme ?? ''));
            $commandeEligibleCredit = (float) $r->commande_montant_medicaments >= $seuilCredit;
            $creditDeduit = $this->commandeCreditDeduit((int) $r->commande_id, $commandeIdsAvecDeduction);

            return [
                'date' => Carbon::parse($r->date)->format('d/m/Y'),
                'produit' => $produit,
                'categorie' => $r->categorie ?: '—',
                'montant' => (float) $r->ligne_montant,
                'commande_eligible_credit' => $commandeEligibleCredit,
                'credit_utilise' => $creditDeduit ? 1 : 0,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{numero: string, client: string, montant: float, statut: string, statut_slug: string, commande_eligible_credit: bool, credit_utilise: bool}>
     */
    private function commandesRecentes(
        CarbonInterface $debut,
        CarbonInterface $fin,
        int $seuilCredit,
    ): array {
        $commandeIdsAvecDeduction = $this->commandeIdsAvecDeductionPeriode($debut, $fin);

        $query = Commande::query()
            ->whereBetween('date', [$debut, $fin])
            ->caComptabilise()
            ->with(['client:id,nom,prenom']);

        if ($this->pharmacieId !== null) {
            $query->where('pharmacie_id', $this->pharmacieId);
        }

        return $query->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Commande $c) use ($seuilCredit, $commandeIdsAvecDeduction) {
                $montantParapharma = $this->montantParapharmaCommande((int) $c->id);

                return [
                    'numero' => $c->numero ?? ('#CMD'.str_pad((string) $c->id, 5, '0', STR_PAD_LEFT)),
                    'client' => trim(($c->client?->prenom ?? '').' '.($c->client?->nom ?? '')) ?: '—',
                    'montant' => $montantParapharma > 0 ? $montantParapharma : (float) $c->prix_medicaments,
                    'statut' => $this->statutLabel($c->status),
                    'statut_slug' => $c->status,
                    'commande_eligible_credit' => $this->commandeEligibleCredit($c, $seuilCredit),
                    'credit_utilise' => $this->commandeCreditDeduit((int) $c->id, $commandeIdsAvecDeduction),
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
        /** @var CommissionHistoriqueService $historiqueService */
        $historiqueService = app(CommissionHistoriqueService::class);
        $items = [];

        for ($i = 1; $i <= 6; $i++) {
            $m = $refCourante->copy()->subMonths($i);

            $periode = $historiqueService->synchroniserPeriode(
                $m->year,
                $m->month,
                $this->pharmacieId,
            );

            if (! $periode) {
                continue;
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
     * @return array<int, array{
     *     pharmacie_id: int,
     *     pharmacie: string,
     *     ca_parapharma: float,
     *     montant_commission: int,
     *     statut: string,
     *     statut_label: string
     * }>
     */
    private function commissionsParPharmacie(
        CarbonInterface $debut,
        CarbonInterface $fin,
        array $cfg,
        CarbonInterface $ref,
    ): array {
        $pharmacieIdCourant = $this->pharmacieId;
        $items = [];

        foreach (Pharmacie::query()->orderBy('designation')->get(['id', 'designation']) as $pharmacie) {
            $this->pharmacieId = (int) $pharmacie->id;

            $ca = $this->sommeCaParapharma($debut, $fin);
            $montant = (int) round($ca * $cfg['commission_percent'] / 100);
            $periode = $this->findCommissionPeriode($ref->year, $ref->month, (int) $pharmacie->id);
            $statut = $periode?->statut ?? CommissionPeriode::STATUT_EN_COURS;

            $items[] = [
                'pharmacie_id' => (int) $pharmacie->id,
                'pharmacie' => $pharmacie->designation,
                'ca_parapharma' => $ca,
                'montant_commission' => $montant,
                'statut' => $statut,
                'statut_label' => $statut === CommissionPeriode::STATUT_PAYE ? 'Payé' : 'En cours',
            ];
        }

        $this->pharmacieId = $pharmacieIdCourant;

        return $items;
    }

    /**
     * @param  list<string>  $values
     */
    private function inListSql(array $values): string
    {
        return collect($values)->map(fn (string $s) => "'".addslashes($s)."'")->implode(',');
    }

    /**
     * CA comptabilisé uniquement après retrait confirmé côté pharmacie.
     *
     * @param  QueryBuilder|Builder<Commande>  $query
     * @return QueryBuilder|Builder<Commande>
     */
    private function whereCaComptabilise(QueryBuilder|Builder $query, string $commandesAlias = 'commandes'): QueryBuilder|Builder
    {
        return $query
            ->where("{$commandesAlias}.status_pharmacie", Commande::STATUT_PHARMACIE_CA_COMPTABILISE)
            ->where("{$commandesAlias}.status", '<>', 'annulee');
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
        $periode = $this->findCommissionPeriode($ref->year, $ref->month, $this->pharmacieId);

        if (! $periode) {
            $periode = CommissionPeriode::query()->create([
                'pharmacie_id' => $this->pharmacieId,
                'annee' => $ref->year,
                'mois' => $ref->month,
                'montant' => $montant,
                'statut' => CommissionPeriode::STATUT_EN_COURS,
            ]);
        } elseif ($periode->statut !== CommissionPeriode::STATUT_PAYE) {
            $periode->update(['montant' => $montant]);
        }

        return $periode->fresh();
    }

    private function findCommissionPeriode(int $annee, int $mois, ?int $pharmacieId): ?CommissionPeriode
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
