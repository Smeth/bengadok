<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Pharmacie;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $pharmacieId = $user->pharmacie_id;

        $baseQuery = Commande::query();
        if ($pharmacieId) {
            $baseQuery->where('pharmacie_id', $pharmacieId);
        }

        $depuisSemaine = now()->startOfWeek();

        $revenuTotal = (clone $baseQuery)
            ->where('date', '>=', $depuisSemaine)
            ->whereIn('status', ['validee', 'livree'])
            ->sum('prix_total');

        $nbPharmacies = $pharmacieId
            ? 1
            : Pharmacie::whereHas('typePharmacie', fn($q) => $q->where('designation', 'like', '%Jour%'))->count();

        $nbCommandes = (clone $baseQuery)
            ->where('date', '>=', $depuisSemaine)
            ->count();

        $nbClients = (clone $baseQuery)
            ->where('date', '>=', $depuisSemaine)
            ->distinct('client_id')
            ->count('client_id');

        $volumeParPharmacie = (clone $baseQuery)
            ->where('date', '>=', now()->startOfMonth())
            ->with('pharmacie')
            ->get()
            ->groupBy('pharmacie_id')
            ->map(fn ($items, $pharmaId) => [
                'pharmacie' => $items->first()?->pharmacie,
                'total' => $items->count(),
            ])
            ->values();

        $volumeParZone = (clone $baseQuery)
            ->where('date', '>=', now()->startOfMonth())
            ->join('pharmacies', 'commandes.pharmacie_id', '=', 'pharmacies.id')
            ->join('zones', 'pharmacies.zone_id', '=', 'zones.id')
            ->selectRaw('zones.designation as zone_name, count(*) as total')
            ->groupBy('zones.id', 'zones.designation')
            ->get();

        return Inertia::render('Dashboard', [
            'kpis' => [
                'revenuTotal' => $revenuTotal,
                'nbPharmacies' => $nbPharmacies,
                'nbCommandes' => $nbCommandes,
                'nbClients' => $nbClients,
            ],
            'volumeParPharmacie' => $volumeParPharmacie,
            'volumeParZone' => $volumeParZone,
        ]);
    }
}
