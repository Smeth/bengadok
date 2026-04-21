<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\DbMedicament;
use App\Models\Pharmacie;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MedicamentController extends Controller
{
    public function index(Request $request): Response
    {
        $onglet = $request->input('onglet', 'catalogue');

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

        $ventesOrderSub = $this->ventesTotalesSubquerySql();

        match ($tri) {
            'prix' => $query->orderBy('pu'),
            'ventes' => $query->orderByRaw("({$ventesOrderSub}) DESC"),
            'designation' => $query->orderBy('designation'),
            default => $query->orderBy('designation'),
        };

        $rankingMap = collect(
            DB::table('commande_produit')
                ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
                ->whereIn('commandes.status', Commande::STATUTS_STATS_VENTES)
                ->where(function ($q) {
                    $q->whereNull('commande_produit.status')
                        ->orWhere('commande_produit.status', '<>', 'indisponible');
                })
                ->selectRaw(
                    'commande_produit.produit_id as produit_id, SUM(COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)) as total',
                )
                ->groupBy('commande_produit.produit_id')
                ->orderByDesc('total')
                ->pluck('produit_id'),
        )
            ->flip()
            ->map(fn ($i) => $i + 1);

        $produits = $query->paginate(15)->withQueryString()->through(function ($p) use ($rankingMap) {
            $prixPharmacy = $p->pharmacies->map(fn ($ph) => (float) ($ph->pivot->prix ?? $p->pu))->filter(fn ($v) => $v > 0)->values();
            $prixMin = $prixPharmacy->isEmpty() ? (float) $p->pu : $prixPharmacy->min();
            $prixMax = $prixPharmacy->isEmpty() ? (float) $p->pu : $prixPharmacy->max();
            $prixMoyen = $prixPharmacy->isEmpty() ? (float) $p->pu : round($prixPharmacy->avg(), 0);
            $ca = $this->caProduitValide($p->id);
            $ventes = $this->ventesProduitValide($p->id);

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

        $dbMedicaments = $onglet === 'db_medicament'
            ? DbMedicament::query()
                ->orderBy('designation')
                ->paginate(15, ['*'], 'db_page')
                ->withQueryString()
                ->through(fn (DbMedicament $m) => [
                    'id' => $m->id,
                    'designation' => $m->designation,
                    'dosage' => $m->dosage,
                    'forme' => $m->forme,
                    'prix' => $m->prix !== null ? (float) $m->prix : null,
                    'laboratoire' => $m->laboratoire,
                    'type' => $m->type,
                    'code_article' => $m->code_article,
                    'notes' => $m->notes,
                    'created_at' => $m->created_at?->format('d/m/Y H:i'),
                ])
            : new LengthAwarePaginator([], 0, 15, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => 'db_page',
            ]);

        return Inertia::render('Medicaments/Index', [
            'produits' => $produits,
            'pharmacies' => $pharmacies,
            'filters' => $request->only(['search', 'type', 'pharmacie_id', 'tri']),
            'onglet' => $onglet,
            'dbMedicaments' => $dbMedicaments,
        ]);
    }

    public function show(Produit $produit): Response
    {
        $ventes = $this->ventesProduitValide($produit->id);

        $ca = $this->caProduitValide($produit->id);

        $allProduitsVentes = DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->whereIn('commandes.status', Commande::STATUTS_STATS_VENTES)
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            })
            ->select(
                'commande_produit.produit_id',
                DB::raw('SUM(COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)) as total'),
            )
            ->groupBy('commande_produit.produit_id')
            ->orderByDesc('total')
            ->get();
        $position = $allProduitsVentes->search(fn ($r) => $r->produit_id == $produit->id);
        $classement = $position !== false ? $position + 1 : null;

        $referencePu = (float) $produit->pu;

        $pharmaciesRows = Pharmacie::query()
            ->orderBy('pharmacies.designation')
            ->leftJoin('produit_pharmacie', function ($join) use ($produit) {
                $join->on('pharmacies.id', '=', 'produit_pharmacie.pharmacie_id')
                    ->where('produit_pharmacie.produit_id', '=', $produit->id);
            })
            ->select('pharmacies.id', 'pharmacies.designation', 'produit_pharmacie.prix as pharmacie_prix')
            ->get();

        $prixParLigne = $pharmaciesRows->map(fn ($row) => (float) ($row->pharmacie_prix ?? $referencePu));
        $prixPositifs = $prixParLigne->filter(fn ($v) => $v > 0)->values();

        if ($prixPositifs->isEmpty()) {
            $prixMin = 0.0;
            $prixMax = 0.0;
            $prixMoyen = 0.0;
        } else {
            $prixMin = (float) $prixPositifs->min();
            $prixMax = (float) $prixPositifs->max();
            $prixMoyen = round((float) $prixPositifs->avg(), 0);
        }

        $ecart = $prixMax - $prixMin;
        $ecartPct = $prixMin > 0 ? (int) round(($ecart / $prixMin) * 100) : 0;
        $plusieursTarifs = $prixMax - $prixMin > 0.009;

        $comparaison = $pharmaciesRows->map(function ($row) use ($referencePu, $prixMin, $prixMax, $plusieursTarifs) {
            $prix = (float) ($row->pharmacie_prix ?? $referencePu);
            $hasPrice = $prix > 0;

            return [
                'id' => (int) $row->id,
                'designation' => $row->designation,
                'prix' => $prix,
                'plus_bas' => $hasPrice && $prixMin > 0 && abs($prix - $prixMin) < 0.01,
                'plus_eleve' => $hasPrice && $plusieursTarifs && abs($prix - $prixMax) < 0.01,
            ];
        })->values()->all();

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

    private function ventesTotalesSubquerySql(): string
    {
        $in = collect(Commande::STATUTS_STATS_VENTES)
            ->map(fn (string $s) => "'".addslashes($s)."'")
            ->implode(',');

        return "SELECT COALESCE(SUM(COALESCE(cp.quantite_confirmee, cp.quantite)), 0) FROM commande_produit cp INNER JOIN commandes c ON c.id = cp.commande_id WHERE cp.produit_id = produits.id AND c.status IN ({$in}) AND (cp.status IS NULL OR cp.status <> 'indisponible')";
    }

    private function ventesProduitValide(int $produitId): int
    {
        return (int) (DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->where('commande_produit.produit_id', $produitId)
            ->whereIn('commandes.status', Commande::STATUTS_STATS_VENTES)
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            })
            ->selectRaw('COALESCE(SUM(COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)), 0) as total')
            ->value('total') ?? 0);
    }

    private function caProduitValide(int $produitId): float
    {
        return (float) (DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->where('commande_produit.produit_id', $produitId)
            ->whereIn('commandes.status', Commande::STATUTS_STATS_VENTES)
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            })
            ->selectRaw('COALESCE(SUM(COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite) * commande_produit.prix_unitaire), 0) as total')
            ->value('total') ?? 0);
    }
}
