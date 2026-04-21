<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\GroupeDoublonsProduit;
use App\Services\ProduitDoublonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MedicamentDoublonController extends Controller
{
    public function __construct(
        private ProduitDoublonService $doublonService
    ) {}

    public function index(Request $request): Response
    {
        $search = (string) $request->input('search', '');
        $statut = (string) $request->input('statut', '');
        $tri = (string) $request->input('tri', 'recent');
        $critere = (string) $request->input('critere', '');

        $criteresActifs = $request->input('criteres', []);
        if (! is_array($criteresActifs)) {
            $criteresActifs = [];
        }
        if (empty($criteresActifs)) {
            $criteresActifs = ['designation_similaire', 'dosage_identique', 'forme_identique'];
        }

        $this->doublonService->detecterEtCreerGroupes($criteresActifs);

        $query = GroupeDoublonsProduit::with(['produits', 'principalProduit']);

        if ($statut !== '') {
            $query->where('statut', $statut);
        }

        $groupes = $query->get();

        if ($critere !== '') {
            $groupes = $groupes->filter(fn ($g) => in_array($critere, $g->criteres ?? [], true))->values();
        }

        if ($search !== '') {
            $s = strtolower($search);
            $groupes = $groupes->filter(function ($g) use ($s) {
                return $g->produits->contains(fn ($p) => str_contains(strtolower($p->designation ?? ''), $s)
                    || str_contains(strtolower($p->dosage ?? ''), $s)
                    || str_contains(strtolower($p->forme ?? ''), $s)
                );
            })->values();
        }

        $ventesMap = $this->getVentesMap();
        $caMap = $this->getCaMap();

        $groupes = match ($tri) {
            'ventes' => $groupes->sortByDesc(fn ($g) => $g->produits->sum(fn ($p) => $ventesMap[$p->id] ?? 0)),
            'ca' => $groupes->sortByDesc(fn ($g) => $g->produits->sum(fn ($p) => $caMap[$p->id] ?? 0)),
            default => $groupes->sortByDesc('created_at'),
        };

        $stats = [
            'en_attente' => GroupeDoublonsProduit::where('statut', 'en_attente')->count(),
            'verifies' => GroupeDoublonsProduit::where('statut', 'verifie')->count(),
            'fusionnes' => GroupeDoublonsProduit::where('statut', 'fusionne')->count(),
            'total_produits' => GroupeDoublonsProduit::with('produits')->get()->sum(fn ($g) => $g->produits->count()),
        ];

        $criteresLabels = ProduitDoublonService::CRITERES_DISPONIBLES;

        $groupesFormates = $groupes->values()->map(function ($g) use ($criteresLabels, $ventesMap, $caMap) {
            $produitsData = $g->produits->map(function ($p) use ($g, $ventesMap, $caMap) {
                $ventes = $ventesMap[$p->id] ?? 0;
                $ca = $caMap[$p->id] ?? 0;

                return [
                    'id' => $p->id,
                    'designation' => $p->designation,
                    'dosage' => $p->dosage,
                    'forme' => $p->forme,
                    'type' => $p->type,
                    'pu' => $p->pu,
                    'ventes' => $ventes,
                    'ca' => $ca,
                    'created_at' => $p->created_at?->format('d/m/Y'),
                    'is_principal' => $p->pivot->is_principal ?? ($p->id === $g->principal_produit_id),
                ];
            });

            $criteres = collect($g->criteres ?? [])->map(fn ($k) => $criteresLabels[$k] ?? $k)->values()->toArray();

            return [
                'id' => $g->id,
                'numero' => str_pad((string) $g->id, 3, '0', STR_PAD_LEFT),
                'statut' => $g->statut,
                'criteres' => $criteres,
                'medicaments' => $produitsData->values()->all(),
                'total_si_fusion' => [
                    'ventes' => $produitsData->sum('ventes'),
                    'ca' => $produitsData->sum('ca'),
                ],
            ];
        });

        return Inertia::render('Medicaments/Doublons', [
            'groupes' => $groupesFormates->values()->all(),
            'stats' => $stats,
            'filters' => $request->only(['search', 'statut', 'tri', 'critere', 'criteres']),
            'criteresDisponibles' => $criteresLabels,
        ]);
    }

    public function ignorer(GroupeDoublonsProduit $groupe): RedirectResponse
    {
        $groupe->update(['statut' => 'ignore']);

        return redirect()->route('medicaments.doublons')->with('success', 'Groupe ignoré.');
    }

    public function verifier(GroupeDoublonsProduit $groupe): RedirectResponse
    {
        $groupe->update(['statut' => 'verifie']);

        return redirect()->route('medicaments.doublons')->with('success', 'Groupe marqué comme vérifié.');
    }

    public function fusionner(Request $request, GroupeDoublonsProduit $groupe): RedirectResponse
    {
        $principalId = (int) $request->input('principal_id');
        if ($principalId < 1) {
            return redirect()->route('medicaments.doublons')->with('error', 'Veuillez sélectionner le médicament à conserver.');
        }
        $groupe->load('produits');
        if (! $groupe->produits->contains('id', $principalId)) {
            return redirect()->route('medicaments.doublons')->with('error', 'Médicament invalide.');
        }
        $this->doublonService->fusionner($groupe, $principalId);

        return redirect()->route('medicaments.doublons')->with('success', 'Médicaments fusionnés avec succès.');
    }

    private function getVentesMap(): array
    {
        return DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->whereIn('commandes.status', Commande::STATUTS_STATS_VENTES)
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            })
            ->selectRaw('commande_produit.produit_id, COALESCE(SUM(COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)), 0) as total')
            ->groupBy('commande_produit.produit_id')
            ->pluck('total', 'produit_id')
            ->toArray();
    }

    private function getCaMap(): array
    {
        return DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->whereIn('commandes.status', Commande::STATUTS_STATS_VENTES)
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            })
            ->selectRaw('commande_produit.produit_id, COALESCE(SUM(COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite) * commande_produit.prix_unitaire), 0) as total')
            ->groupBy('commande_produit.produit_id')
            ->pluck('total', 'produit_id')
            ->toArray();
    }
}
