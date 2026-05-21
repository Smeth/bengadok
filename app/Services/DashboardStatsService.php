<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Commande;
use App\Models\MotifAnnulation;
use App\Models\Pharmacie;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    /** @var list<string> */
    public const STATUTS_REUSSIS = ['retiree', 'livree'];

    /**
     * @return array{
     *     period: string,
     *     kpis: array<string, mixed>,
     *     volumeParPharmacie: Collection,
     *     volumeParZone: Collection,
     *     revenusParJour: array<int, array{jour: string, label: string, total: float}>,
     *     annulationsParMotif: array<int, array{slug: string, label: string, total: int}>,
     *     tauxCommandes: array{reussies: int, annulees: int, autres: int, taux_reussite: float|null, taux_annulation: float|null},
     *     canauxAcquisition: array<int, array{canal: string, label: string, total: int}>,
     *     topMedicaments: array<int, array{designation: string, dosage: string|null, forme: string|null, ventes: int}>,
     *     delais: array{reponse_pharmacie_heures: float|null, livraison_heures: float|null, nb_reponse_pharmacie: int, nb_livraison: int}
     * }
     */
    public function build(?int $pharmacieId, string $period): array
    {
        $period = in_array($period, ['day', 'week', 'month'], true) ? $period : 'month';
        $bounds = $this->resolvePeriodBounds($period);
        $currentStart = $bounds['currentStart'];
        $currentEnd = $bounds['currentEnd'];
        $prevStart = $bounds['prevStart'];
        $prevEnd = $bounds['prevEnd'];

        $baseQuery = Commande::query();
        if ($pharmacieId) {
            $baseQuery->where('pharmacie_id', $pharmacieId);
        }

        $colRevenu = $pharmacieId ? 'prix_medicaments' : 'prix_total';

        $revenuTotal = (float) (clone $baseQuery)
            ->whereBetween('date', [$currentStart, $currentEnd])
            ->whereIn('status', self::STATUTS_REUSSIS)
            ->sum($colRevenu);

        $revenuPrec = (float) (clone $baseQuery)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->whereIn('status', self::STATUTS_REUSSIS)
            ->sum($colRevenu);

        $nbPharmacies = $pharmacieId ? 1 : Pharmacie::query()->count();

        $nbPharmaciesActives = $pharmacieId
            ? 1
            : (int) (clone $baseQuery)
                ->whereBetween('date', [$currentStart, $currentEnd])
                ->distinct('pharmacie_id')
                ->count('pharmacie_id');

        $nbPharmaciesActivesPrec = $pharmacieId
            ? 1
            : (int) Commande::query()
                ->whereBetween('date', [$prevStart, $prevEnd])
                ->distinct('pharmacie_id')
                ->count('pharmacie_id');

        $nbCommandes = (int) (clone $baseQuery)
            ->whereBetween('date', [$currentStart, $currentEnd])
            ->count();

        $nbCommandesPrec = (int) (clone $baseQuery)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->count();

        $nbClients = (int) (clone $baseQuery)
            ->whereBetween('date', [$currentStart, $currentEnd])
            ->distinct('client_id')
            ->count('client_id');

        $nbClientsPrec = (int) (clone $baseQuery)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->distinct('client_id')
            ->count('client_id');

        $nbReussies = (int) (clone $baseQuery)
            ->whereBetween('date', [$currentStart, $currentEnd])
            ->whereIn('status', self::STATUTS_REUSSIS)
            ->count();

        $nbAnnulees = (int) (clone $baseQuery)
            ->whereBetween('date', [$currentStart, $currentEnd])
            ->where('status', 'annulee')
            ->count();

        $nbTerminees = $nbReussies + $nbAnnulees;

        $panierMoyen = $nbReussies > 0
            ? round((float) (clone $baseQuery)
                ->whereBetween('date', [$currentStart, $currentEnd])
                ->whereIn('status', self::STATUTS_REUSSIS)
                ->avg($colRevenu), 0)
            : 0;

        $panierPrec = (clone $baseQuery)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->whereIn('status', self::STATUTS_REUSSIS)
            ->avg($colRevenu);
        $panierPrec = $panierPrec !== null ? round((float) $panierPrec, 0) : 0;

        $volumeParPharmacie = (clone $baseQuery)
            ->whereBetween('date', [$currentStart, $currentEnd])
            ->with('pharmacie:id,designation')
            ->get()
            ->groupBy('pharmacie_id')
            ->map(fn ($items) => [
                'pharmacie' => $items->first()?->pharmacie,
                'total' => $items->count(),
            ])
            ->sortByDesc('total')
            ->take(8)
            ->values();

        try {
            $volumeParZone = (clone $baseQuery)
                ->whereBetween('commandes.date', [$currentStart, $currentEnd])
                ->join('pharmacies', 'commandes.pharmacie_id', '=', 'pharmacies.id')
                ->whereNotNull('pharmacies.zone_id')
                ->join('zones', 'pharmacies.zone_id', '=', 'zones.id')
                ->selectRaw('zones.designation as zone_name, count(*) as total')
                ->groupBy('zones.id', 'zones.designation')
                ->get();
        } catch (\Throwable) {
            $volumeParZone = collect();
        }

        $annulationsParMotif = $this->annulationsParMotif($baseQuery, $currentStart, $currentEnd);

        $canauxAcquisition = $this->canauxAcquisition($baseQuery, $currentStart, $currentEnd);

        $topMedicaments = $this->topMedicaments($currentStart, $currentEnd, $pharmacieId);

        $delais = $this->delaisMoyens($baseQuery, $currentStart, $currentEnd);

        $revenusParJour = $pharmacieId
            ? $this->revenusParJour($baseQuery, $currentStart, $currentEnd, $period)
            : [];

        return [
            'period' => $period,
            'kpis' => [
                'revenuTotal' => $revenuTotal,
                'nbPharmacies' => $nbPharmacies,
                'nbPharmaciesActives' => $nbPharmaciesActives,
                'nbCommandes' => $nbCommandes,
                'nbClients' => $nbClients,
                'nbReussies' => $nbReussies,
                'nbAnnulees' => $nbAnnulees,
                'panierMoyen' => $panierMoyen,
                'evolutionRevenu' => $this->evolutionPct($revenuTotal, $revenuPrec),
                'evolutionPharmaciesActives' => $this->evolutionPct($nbPharmaciesActives, $nbPharmaciesActivesPrec),
                'evolutionCommandes' => $this->evolutionPct($nbCommandes, $nbCommandesPrec),
                'evolutionClients' => $this->evolutionPct($nbClients, $nbClientsPrec),
                'evolutionPanierMoyen' => $this->evolutionPct($panierMoyen, $panierPrec),
            ],
            'volumeParPharmacie' => $volumeParPharmacie,
            'volumeParZone' => $volumeParZone,
            'revenusParJour' => $revenusParJour,
            'annulationsParMotif' => $annulationsParMotif,
            'tauxCommandes' => [
                'reussies' => $nbReussies,
                'annulees' => $nbAnnulees,
                'autres' => max(0, $nbCommandes - $nbTerminees),
                'taux_reussite' => $nbTerminees > 0 ? round($nbReussies / $nbTerminees * 100, 1) : null,
                'taux_annulation' => $nbCommandes > 0 ? round($nbAnnulees / $nbCommandes * 100, 1) : null,
            ],
            'canauxAcquisition' => $canauxAcquisition,
            'topMedicaments' => $topMedicaments,
            'delais' => $delais,
        ];
    }

    /**
     * @return array{currentStart: CarbonInterface, currentEnd: CarbonInterface, prevStart: CarbonInterface, prevEnd: CarbonInterface}
     */
    private function resolvePeriodBounds(string $period): array
    {
        $now = now();

        if ($period === 'day') {
            return [
                'currentStart' => $now->copy()->startOfDay(),
                'currentEnd' => $now->copy()->endOfDay(),
                'prevStart' => $now->copy()->subDay()->startOfDay(),
                'prevEnd' => $now->copy()->subDay()->endOfDay(),
            ];
        }

        if ($period === 'week') {
            $currentStart = $now->copy()->startOfWeek();
            $currentEnd = $now->copy()->endOfDay();
            $nDays = (int) $currentStart->diffInDays($currentEnd);

            return [
                'currentStart' => $currentStart,
                'currentEnd' => $currentEnd,
                'prevStart' => $currentStart->copy()->subWeek(),
                'prevEnd' => $currentStart->copy()->subWeek()->addDays($nDays)->endOfDay(),
            ];
        }

        $currentStart = $now->copy()->startOfMonth();
        $currentEnd = $now->copy()->endOfDay();
        $dayCount = (int) $currentStart->diffInDays($currentEnd) + 1;
        $prevStart = $now->copy()->subMonth()->startOfMonth();
        $prevEnd = $prevStart->copy()->addDays($dayCount - 1)->endOfDay();
        $maxPrev = $now->copy()->subMonth()->endOfMonth();
        if ($prevEnd->gt($maxPrev)) {
            $prevEnd = $maxPrev;
        }

        return [
            'currentStart' => $currentStart,
            'currentEnd' => $currentEnd,
            'prevStart' => $prevStart,
            'prevEnd' => $prevEnd,
        ];
    }

    private function evolutionPct(float|int $current, float|int $previous): int
    {
        if ($previous > 0) {
            return (int) round((($current - $previous) / $previous) * 100);
        }

        return $current > 0 ? 100 : 0;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Commande>  $baseQuery
     * @return array<int, array{slug: string, label: string, total: int}>
     */
    private function annulationsParMotif($baseQuery, CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = (clone $baseQuery)
            ->whereBetween('date', [$start, $end])
            ->where('status', 'annulee')
            ->whereNotNull('motif_annulation')
            ->selectRaw('motif_annulation as slug, count(*) as total')
            ->groupBy('motif_annulation')
            ->orderByDesc('total')
            ->get();

        $labels = MotifAnnulation::query()
            ->whereIn('slug', $rows->pluck('slug'))
            ->pluck('label', 'slug');

        return $rows->map(fn ($r) => [
            'slug' => $r->slug,
            'label' => $labels[$r->slug] ?? $r->slug,
            'total' => (int) $r->total,
        ])->values()->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Commande>  $baseQuery
     * @return array<int, array{canal: string, label: string, total: int}>
     */
    private function canauxAcquisition($baseQuery, CarbonInterface $start, CarbonInterface $end): array
    {
        $clientIds = (clone $baseQuery)
            ->whereBetween('date', [$start, $end])
            ->distinct()
            ->pluck('client_id');

        if ($clientIds->isEmpty()) {
            return [];
        }

        $counts = Client::query()
            ->whereIn('id', $clientIds)
            ->selectRaw("COALESCE(NULLIF(canal_acquisition, ''), 'non_renseigne') as canal, count(*) as total")
            ->groupBy('canal')
            ->orderByDesc('total')
            ->get();

        return $counts->map(function ($row) {
            $canal = $row->canal;
            $label = $canal === 'non_renseigne'
                ? 'Non renseigné'
                : (Client::CANAL_LABELS[$canal] ?? $canal);

            return [
                'canal' => $canal,
                'label' => $label,
                'total' => (int) $row->total,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{designation: string, dosage: string|null, forme: string|null, ventes: int}>
     */
    private function topMedicaments(CarbonInterface $start, CarbonInterface $end, ?int $pharmacieId = null): array
    {
        $in = collect(Commande::STATUTS_STATS_VENTES)
            ->map(fn (string $s) => "'".addslashes($s)."'")
            ->implode(',');

        $query = DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
            ->whereBetween('commandes.date', [$start, $end])
            ->whereRaw("commandes.status IN ({$in})")
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            });

        if ($pharmacieId) {
            $query->where('commandes.pharmacie_id', $pharmacieId);
        }

        return $query
            ->selectRaw('
                produits.designation,
                produits.dosage,
                produits.forme,
                SUM(COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)) as ventes
            ')
            ->groupBy('produits.id', 'produits.designation', 'produits.dosage', 'produits.forme')
            ->orderByDesc('ventes')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'designation' => $r->designation,
                'dosage' => $r->dosage,
                'forme' => $r->forme,
                'ventes' => (int) $r->ventes,
            ])
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Commande>  $baseQuery
     * @return array{reponse_pharmacie_heures: float|null, livraison_heures: float|null, nb_reponse_pharmacie: int, nb_livraison: int}
     */
    private function delaisMoyens($baseQuery, CarbonInterface $start, CarbonInterface $end): array
    {
        $reponseRows = (clone $baseQuery)
            ->whereBetween('date', [$start, $end])
            ->whereNotNull('dispo_pharmacie_at')
            ->get(['created_at', 'dispo_pharmacie_at']);

        $nbReponse = $reponseRows->count();
        $avgReponseMinutes = $nbReponse > 0
            ? $reponseRows->avg(fn (Commande $c) => $c->created_at->diffInMinutes($c->dispo_pharmacie_at))
            : null;

        $livraisonRows = (clone $baseQuery)
            ->whereBetween('date', [$start, $end])
            ->whereNotNull('validee_admin_at')
            ->whereNotNull('livree_at')
            ->whereIn('status', self::STATUTS_REUSSIS)
            ->get(['validee_admin_at', 'livree_at']);

        $nbLivraison = $livraisonRows->count();
        $avgLivraisonMinutes = $nbLivraison > 0
            ? $livraisonRows->avg(fn (Commande $c) => $c->validee_admin_at->diffInMinutes($c->livree_at))
            : null;

        return [
            'reponse_pharmacie_heures' => $avgReponseMinutes !== null
                ? round((float) $avgReponseMinutes / 60, 1)
                : null,
            'livraison_heures' => $avgLivraisonMinutes !== null
                ? round((float) $avgLivraisonMinutes / 60, 1)
                : null,
            'nb_reponse_pharmacie' => $nbReponse,
            'nb_livraison' => $nbLivraison,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Commande>  $baseQuery
     * @return array<int, array{jour: string, label: string, total: float}>
     */
    private function revenusParJour($baseQuery, CarbonInterface $start, CarbonInterface $end, string $period): array
    {
        $jourDebut = $start->copy();
        $jourFin = $end->copy();
        $nbJours = (int) $jourDebut->diffInDays($jourFin) + 1;
        $maxJours = match ($period) {
            'day' => 1,
            'week' => 7,
            default => 31,
        };
        $nbJours = min($nbJours, $maxJours);

        $revenusBruts = (clone $baseQuery)
            ->whereBetween('date', [$jourDebut, $jourFin])
            ->whereIn('status', self::STATUTS_REUSSIS)
            ->selectRaw('DATE(date) as jour, COALESCE(SUM(prix_medicaments), 0) as total')
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy('jour')
            ->get()
            ->keyBy('jour');

        $result = [];
        for ($i = 0; $i < $nbJours; $i++) {
            $d = $jourDebut->copy()->addDays($i);
            $key = $d->format('Y-m-d');
            $result[] = [
                'jour' => $key,
                'label' => $period === 'day' ? $d->format('H') : $d->format('d'),
                'total' => (float) ($revenusBruts->get($key)?->total ?? 0),
            ];
        }

        return $result;
    }
}
