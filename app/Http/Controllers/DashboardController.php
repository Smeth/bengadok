<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Pharmacie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $pharmacieId = $user->pharmacie_id;

        $period = $request->get('period', 'month');
        if (! in_array($period, ['week', 'month'], true)) {
            $period = 'month';
        }

        $now = now();
        if ($period === 'week') {
            $currentStart = $now->copy()->startOfWeek();
            $currentEnd = $now->copy()->endOfDay();
            $nDays = (int) $currentStart->diffInDays($currentEnd);
            $prevStart = $currentStart->copy()->subWeek();
            $prevEnd = $prevStart->copy()->addDays($nDays)->endOfDay();
        } else {
            $currentStart = $now->copy()->startOfMonth();
            $currentEnd = $now->copy()->endOfDay();
            $dayCount = (int) $currentStart->diffInDays($currentEnd) + 1;
            $prevStart = $now->copy()->subMonth()->startOfMonth();
            $prevEnd = $prevStart->copy()->addDays($dayCount - 1)->endOfDay();
            $maxPrev = $now->copy()->subMonth()->endOfMonth();
            if ($prevEnd->gt($maxPrev)) {
                $prevEnd = $maxPrev;
            }
        }

        $chartStart = $currentStart;

        $baseQuery = Commande::query();
        if ($pharmacieId) {
            $baseQuery->where('pharmacie_id', $pharmacieId);
        }

        $colRevenu = $pharmacieId ? 'prix_medicaments' : 'prix_total';

        $revenuTotal = (clone $baseQuery)
            ->whereBetween('date', [$currentStart, $currentEnd])
            ->whereIn('status', ['validee', 'retiree'])
            ->sum($colRevenu);

        $revenuSemainePrec = (clone $baseQuery)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->whereIn('status', ['validee', 'retiree'])
            ->sum($colRevenu);

        $nbPharmacies = $pharmacieId
            ? 1
            : Pharmacie::whereHas('typePharmacie', fn ($q) => $q->where('designation', 'like', '%Jour%'))->count();

        $nbPharmaciesPrec = $pharmacieId
            ? 1
            : (int) Commande::query()
                ->whereBetween('date', [$prevStart, $prevEnd])
                ->whereIn('status', ['validee', 'retiree'])
                ->selectRaw('COUNT(DISTINCT pharmacie_id) as aggregate')
                ->value('aggregate');

        $nbPharmaciesActives = $pharmacieId
            ? 1
            : (int) (clone $baseQuery)
                ->whereBetween('date', [$currentStart, $currentEnd])
                ->whereIn('status', ['validee', 'retiree'])
                ->distinct('pharmacie_id')
                ->count('pharmacie_id');

        $nbCommandes = (clone $baseQuery)
            ->whereBetween('date', [$currentStart, $currentEnd])
            ->count();

        $nbCommandesPrec = (clone $baseQuery)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->count();

        $nbClients = (int) (clone $baseQuery)
            ->whereBetween('date', [$currentStart, $currentEnd])
            ->selectRaw('COUNT(DISTINCT client_id) as aggregate')
            ->value('aggregate');

        $nbClientsPrec = (int) (clone $baseQuery)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->selectRaw('COUNT(DISTINCT client_id) as aggregate')
            ->value('aggregate');

        $evolutionRevenu = $revenuSemainePrec > 0
            ? (int) round((($revenuTotal - $revenuSemainePrec) / $revenuSemainePrec) * 100)
            : ($revenuTotal > 0 ? 100 : 0);

        $evolutionCommandes = $nbCommandesPrec > 0
            ? (int) round((($nbCommandes - $nbCommandesPrec) / $nbCommandesPrec) * 100)
            : ($nbCommandes > 0 ? 100 : 0);

        $evolutionClients = $nbClientsPrec > 0
            ? (int) round((($nbClients - $nbClientsPrec) / $nbClientsPrec) * 100)
            : ($nbClients > 0 ? 100 : 0);

        $evolutionPharmacies = $pharmacieId
            ? 0
            : ($nbPharmaciesPrec > 0
                ? (int) round((($nbPharmaciesActives - $nbPharmaciesPrec) / $nbPharmaciesPrec) * 100)
                : ($nbPharmaciesActives > 0 ? 100 : 0));

        $volumeParPharmacie = (clone $baseQuery)
            ->where('date', '>=', $chartStart)
            ->with('pharmacie')
            ->get()
            ->groupBy('pharmacie_id')
            ->map(fn ($items, $pharmaId) => [
                'pharmacie' => $items->first()?->pharmacie,
                'total' => $items->count(),
            ])
            ->sortByDesc('total')
            ->take(8)
            ->values();

        try {
            $volumeParZone = (clone $baseQuery)
                ->where('date', '>=', $chartStart)
                ->join('pharmacies', 'commandes.pharmacie_id', '=', 'pharmacies.id')
                ->whereNotNull('pharmacies.zone_id')
                ->join('zones', 'pharmacies.zone_id', '=', 'zones.id')
                ->selectRaw('zones.designation as zone_name, count(*) as total')
                ->groupBy('zones.id', 'zones.designation')
                ->get();
        } catch (\Throwable $e) {
            \Log::warning('Dashboard: erreur volumeParZone', ['exception' => $e->getMessage()]);
            $volumeParZone = collect();
        }

        $revenusParJour = [];

        if ($pharmacieId) {
            $jourDebut = $currentStart->copy();
            $jourFin = $currentEnd->copy();
            $nbJours = (int) $jourDebut->diffInDays($jourFin) + 1;
            $nbJours = min($nbJours, $period === 'week' ? 7 : 31);

            $revenusBruts = (clone $baseQuery)
                ->whereBetween('date', [$jourDebut, $jourFin])
                ->whereIn('status', ['validee', 'retiree'])
                ->selectRaw('DATE(date) as jour, COALESCE(SUM(prix_medicaments), 0) as total')
                ->groupBy(DB::raw('DATE(date)'))
                ->orderBy('jour')
                ->get()
                ->keyBy('jour');

            $revenusParJour = collect();
            for ($i = 0; $i < $nbJours; $i++) {
                $d = $jourDebut->copy()->addDays($i);
                $key = $d->format('Y-m-d');
                $revenusParJour->push([
                    'jour' => $key,
                    'label' => $d->format('d'),
                    'total' => (float) ($revenusBruts->get($key)?->total ?? 0),
                ]);
            }
            $revenusParJour = $revenusParJour->values()->toArray();
        }

        return Inertia::render('Dashboard', [
            'period' => $period,
            'kpis' => [
                'revenuTotal' => $revenuTotal,
                'nbPharmacies' => $nbPharmacies,
                'nbCommandes' => $nbCommandes,
                'nbClients' => $nbClients,
                'evolutionRevenu' => $evolutionRevenu,
                'evolutionPharmacies' => $evolutionPharmacies,
                'evolutionCommandes' => $evolutionCommandes,
                'evolutionClients' => $evolutionClients,
            ],
            'volumeParPharmacie' => $volumeParPharmacie,
            'volumeParZone' => $volumeParZone,
            'revenusParJour' => $revenusParJour,
        ]);
    }
}
