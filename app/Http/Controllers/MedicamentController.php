<?php

namespace App\Http\Controllers;

use App\Models\Pharmacie;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MedicamentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $type = $request->input('type', '');
        $pharmacieId = $request->input('pharmacie_id', '');
        $tri = $request->input('tri', 'designation');

        $query = Produit::query()
            ->with(['pharmacies' => fn ($q) => $q->select('pharmacies.id', 'pharmacies.designation')->withPivot('prix', 'stock')]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('designation', 'like', "%{$search}%")
                    ->orWhere('dosage', 'like', "%{$search}%")
                    ->orWhere('forme', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($pharmacieId) {
            $query->whereHas('pharmacies', fn ($q) => $q->where('pharmacies.id', (int) $pharmacieId));
        }

        match ($tri) {
            'prix' => $query->orderBy('pu'),
            'ventes' => $query->orderByRaw('(SELECT COALESCE(SUM(cp.quantite), 0) FROM commande_produit cp WHERE cp.produit_id = produits.id) DESC'),
            'designation' => $query->orderBy('designation'),
            default => $query->orderBy('designation'),
        };

        $rankingMap = collect(DB::table('commande_produit')
            ->select('produit_id', DB::raw('SUM(quantite) as total'))
            ->groupBy('produit_id')
            ->orderByDesc('total')
            ->pluck('produit_id'))
            ->flip()
            ->map(fn ($i) => $i + 1);

        $produits = $query->paginate(15)->withQueryString()->through(function ($p) use ($rankingMap) {
            $prixPharmacy = $p->pharmacies->map(fn ($ph) => (float) ($ph->pivot->prix ?? $p->pu))->filter(fn ($v) => $v > 0)->values();
            $prixMin = $prixPharmacy->isEmpty() ? (float) $p->pu : $prixPharmacy->min();
            $prixMax = $prixPharmacy->isEmpty() ? (float) $p->pu : $prixPharmacy->max();
            $prixMoyen = $prixPharmacy->isEmpty() ? (float) $p->pu : round($prixPharmacy->avg(), 0);
            $ca = DB::table('commande_produit')
                ->where('produit_id', $p->id)
                ->selectRaw('COALESCE(SUM(quantite * prix_unitaire), 0) as total')
                ->value('total') ?? 0;
            $ventes = DB::table('commande_produit')
                ->where('produit_id', $p->id)
                ->selectRaw('COALESCE(SUM(quantite), 0) as total')
                ->value('total') ?? 0;

            return [
                'id' => $p->id,
                'designation' => $p->designation,
                'dosage' => $p->dosage,
                'forme' => $p->forme,
                'type' => $p->type ?? 'Vente libre',
                'pu' => $p->pu,
                'created_at' => $p->created_at?->format('d/m/Y'),
                'ventes' => (int) $ventes,
                'ca' => (float) $ca,
                'prix_moyen' => $prixMoyen,
                'prix_min' => (float) $prixMin,
                'prix_max' => (float) $prixMax,
                'classement' => $rankingMap[$p->id] ?? null,
                'pharmacies' => $p->pharmacies
                    ->sortByDesc(fn ($ph) => $ph->pivot->updated_at?->getTimestamp() ?? 0)
                    ->take(2)
                    ->values()
                    ->map(fn ($ph) => [
                        'id' => $ph->id,
                        'designation' => $ph->designation,
                        'prix' => $ph->pivot->prix ?? $p->pu,
                    ]),
            ];
        });

        $pharmacies = Pharmacie::orderBy('designation')->get(['id', 'designation']);

        return Inertia::render('Medicaments/Index', [
            'produits' => $produits,
            'pharmacies' => $pharmacies,
            'filters' => $request->only(['search', 'type', 'pharmacie_id', 'tri']),
        ]);
    }

    public function show(Produit $produit): Response
    {
        $produit->load(['pharmacies' => fn ($q) => $q->orderBy('designation')->withPivot('prix', 'stock')]);

        $ventes = DB::table('commande_produit')
            ->where('produit_id', $produit->id)
            ->selectRaw('COALESCE(SUM(quantite), 0) as total')
            ->value('total') ?? 0;

        $ca = DB::table('commande_produit')
            ->where('produit_id', $produit->id)
            ->selectRaw('COALESCE(SUM(quantite * prix_unitaire), 0) as total')
            ->value('total') ?? 0;

        $allProduitsVentes = DB::table('commande_produit')
            ->select('produit_id', DB::raw('SUM(quantite) as total'))
            ->groupBy('produit_id')
            ->orderByDesc('total')
            ->get();
        $position = $allProduitsVentes->search(fn ($r) => $r->produit_id == $produit->id);
        $classement = $position !== false ? $position + 1 : null;

        $prixList = $produit->pharmacies->map(fn ($ph) => (float) ($ph->pivot->prix ?? $produit->pu))->filter(fn ($v) => $v > 0)->values();
        $prixMin = $prixList->isEmpty() ? (float) $produit->pu : $prixList->min();
        $prixMax = $prixList->isEmpty() ? (float) $produit->pu : $prixList->max();
        $prixMoyen = $prixList->isEmpty() ? (float) $produit->pu : round($prixList->avg(), 0);
        $ecart = $prixMax - $prixMin;
        $ecartPct = $prixMin > 0 ? round(($ecart / $prixMin) * 100) : 0;

        $comparaison = $produit->pharmacies->map(function ($ph) use ($produit, $prixMin, $prixMax) {
            $prix = (float) ($ph->pivot->prix ?? $produit->pu);

            return [
                'id' => $ph->id,
                'designation' => $ph->designation,
                'prix' => $prix,
                'plus_bas' => $prix > 0 && abs($prix - $prixMin) < 0.01,
                'plus_eleve' => $prix > 0 && abs($prix - $prixMax) < 0.01,
            ];
        });

        return Inertia::render('Medicaments/Show', [
            'produit' => [
                'id' => $produit->id,
                'designation' => $produit->designation,
                'dosage' => $produit->dosage,
                'forme' => $produit->forme,
                'type' => $produit->type ?? 'Vente libre',
                'created_at' => $produit->created_at?->format('d/m/Y'),
                'ventes' => (int) $ventes,
                'ca' => (float) $ca,
                'classement' => $classement,
                'prix_moyen' => $prixMoyen,
                'prix_min' => $prixMin,
                'prix_max' => $prixMax,
                'ecart' => $ecart,
                'ecart_pct' => $ecartPct,
                'comparaison' => $comparaison,
            ],
        ]);
    }
}
