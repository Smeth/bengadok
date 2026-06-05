<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Produit;
use App\Services\PharmacieDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DokPharmaController extends Controller
{
    /**
     * Extrait une heure HH:MM depuis le champ métier `heurs` (ex. "14:30" ou plage "08:00-19:00").
     */
    private function extractHeureCommande(?string $heursRaw): ?string
    {
        if ($heursRaw === null || trim($heursRaw) === '') {
            return null;
        }
        $s = trim($heursRaw);
        if (preg_match('/^(\d{1,2}:\d{2})$/', $s, $m)) {
            return $m[1];
        }
        if (preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $s, $m)) {
            return $m[1];
        }
        if (preg_match('/(\d{1,2}:\d{2})/', $s, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Affichage cohérent : date métier + heure (fuseau application).
     * Si `heurs` est absent ou illisible, on utilise l'heure de création en base.
     */
    private function formatCommandeDateHeure(Commande $c): string
    {
        $tz = (string) config('app.timezone');

        if ($c->date) {
            $time = $this->extractHeureCommande($c->heurs ? (string) $c->heurs : null);
            if ($time === null && $c->created_at) {
                $time = $c->created_at->copy()->timezone($tz)->format('H:i');
            }
            if ($time === null) {
                $time = '00:00';
            }
            $datePart = $c->date instanceof \Carbon\CarbonInterface
                ? $c->date->format('Y-m-d')
                : \Carbon\Carbon::parse($c->date)->format('Y-m-d');

            return \Carbon\Carbon::parse($datePart.' '.$time, $tz)->format('d/m/Y H:i');
        }

        if ($c->created_at) {
            return $c->created_at->copy()->timezone($tz)->format('d/m/Y H:i');
        }

        return '';
    }

    /**
     * Dashboard pharmacie : stats, graphiques, meilleures ventes.
     */
    public function dashboard(Request $request, PharmacieDashboardService $dashboardService): Response
    {
        $user = $request->user();
        if ($user && $user->hasRole('vendeur') && ! $user->hasRole('gerant')) {
            return redirect('/dok-pharma/commandes');
        }

        $pharmacieId = $request->user()?->pharmacie_id;
        if (! $pharmacieId) {
            $period = in_array($request->query('period'), ['week', 'month'], true)
                ? $request->query('period')
                : 'month';
            $chartOffset = min(0, max(-52, (int) $request->query('chart_offset', 0)));

            return Inertia::render(
                'DokPharma/Dashboard',
                $dashboardService->emptyPayload($period, $chartOffset),
            );
        }

        $period = in_array($request->query('period'), ['week', 'month'], true)
            ? $request->query('period')
            : 'month';
        $chartOffset = (int) $request->query('chart_offset', 0);

        return Inertia::render(
            'DokPharma/Dashboard',
            $dashboardService->build($pharmacieId, $period, $chartOffset),
        );
    }

    public function index(Request $request): Response
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (! $pharmacieId) {
            return Inertia::render('DokPharma/Index', [
                'commandes' => ['data' => [], 'links' => [], 'current_page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0, 'total' => 0],
                'stats' => ['nouvelles' => 0, 'en_attente' => 0, 'a_preparer' => 0, 'livrees' => 0],
                'onglet' => $request->input('onglet', 'nouvelles'),
                'search' => Str::limit(trim((string) $request->input('search', '')), 100, ''),
            ]);
        }

        $onglet = $request->input('onglet', 'nouvelles');
        $search = Str::limit(trim((string) $request->input('search', '')), 100, '');

        $query = Commande::with(['client', 'produits', 'ordonnance'])
            ->where('pharmacie_id', $pharmacieId);

        $query
            ->when($onglet === 'nouvelles', fn ($q) => $q->where('status_pharmacie', 'nouvelle'))
            ->when($onglet === 'en_attente', fn ($q) => $q->whereIn('status_pharmacie', ['attente_confirmation', 'indisponible']))
            ->when($onglet === 'a_preparer', fn ($q) => $q->where('status_pharmacie', 'valide_a_preparer'))
            ->when($onglet === 'livrees', fn ($q) => $q->where('status_pharmacie', 'livre'))
            ->when(! in_array($onglet, ['nouvelles', 'en_attente', 'a_preparer', 'livrees']),
                fn ($q) => $q->where('status_pharmacie', 'nouvelle'));

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('numero', 'like', $like)
                    ->orWhereHas('client', function ($cq) use ($like) {
                        $cq->where('nom', 'like', $like)
                            ->orWhere('prenom', 'like', $like);
                    })
                    ->orWhereHas('produits', function ($pq) use ($like) {
                        $pq->where('designation', 'like', $like);
                    });
            });
        }

        $commandes = $query
            ->latest('date')
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($c) {
                return [
                    'id' => $c->id,
                    'numero' => $c->numero,
                    'date' => $this->formatCommandeDateHeure($c),
                    'status' => $c->status,
                    'status_pharmacie' => $c->status_pharmacie,
                    'client' => $c->client
                        ? [
                            'nom' => $c->client->nom,
                            'prenom' => $c->client->prenom,
                            'sexe' => $c->client->sexe,
                        ]
                        : null,
                    'produits' => $c->produits->map(fn ($p) => [
                        'id' => $p->id,
                        'designation' => $p->designation,
                        'pivot' => [
                            'quantite' => $p->pivot->quantite,
                            'prix_unitaire' => (float) ($p->pivot->prix_unitaire ?? 0),
                            'status' => $p->pivot->status ?? 'disponible',
                            'quantite_confirmee' => $p->pivot->quantite_confirmee ?? null,
                            'vente_libre' => (bool) ($p->pivot->vente_libre ?? false),
                        ],
                    ])->values(),
                    'ordonnance_id' => $c->ordonnance_id,
                    'ordonnance_url' => $c->ordonnance?->urlfile
                        ? asset('storage/'.ltrim($c->ordonnance->urlfile, '/'))
                        : null,
                    'commentaire' => $c->commentaire,
                    'commentaire_pharmacie' => $c->commentaire_pharmacie,
                    'prix_medicaments' => (float) ($c->prix_medicaments ?? 0),
                ];
            });

        $stats = [
            'nouvelles' => Commande::where('pharmacie_id', $pharmacieId)->where('status_pharmacie', 'nouvelle')->count(),
            'en_attente' => Commande::where('pharmacie_id', $pharmacieId)->whereIn('status_pharmacie', ['attente_confirmation', 'indisponible'])->count(),
            'a_preparer' => Commande::where('pharmacie_id', $pharmacieId)->where('status_pharmacie', 'valide_a_preparer')->count(),
            'livrees' => Commande::where('pharmacie_id', $pharmacieId)->where('status_pharmacie', 'livre')->count(),
        ];

        return Inertia::render('DokPharma/Index', [
            'commandes' => $commandes,
            'stats' => $stats,
            'onglet' => $onglet,
            'search' => $search,
        ]);
    }

    /**
     * La pharmacie valide la disponibilité + prix des médicaments.
     */
    public function validerDisponibilite(Request $request, Commande $commande)
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (! $pharmacieId || $commande->pharmacie_id != $pharmacieId) {
            abort(403);
        }

        // Charger les produits pour connaître les quantités demandées
        $commande->load('produits');
        $produitMap = $commande->produits->keyBy('id');

        $lignes = $request->input('lignes', []);
        $nbDispo = 0;
        $nbIndispo = 0;

        // Vérification préalable : tout produit disponible doit avoir un prix > 0
        foreach ($lignes as $ligne) {
            $status = $ligne['status'] ?? 'disponible';
            $prixUnitaire = isset($ligne['prix_unitaire']) ? (float) $ligne['prix_unitaire'] : 0;
            if (in_array($status, ['disponible', 'partiel']) && $prixUnitaire <= 0) {
                return back()->with('error', 'Veuillez saisir le prix pour tous les médicaments disponibles avant d\'envoyer.');
            }
        }

        foreach ($lignes as $ligne) {
            $produitId = (int) ($ligne['produit_id'] ?? 0);
            $produit = $produitMap->get($produitId);
            if (! $produit) {
                continue;
            }

            $qteDemandee = (int) $produit->pivot->quantite;
            $status = $ligne['status'] ?? 'disponible';
            $prixUnitaire = isset($ligne['prix_unitaire']) ? (float) $ligne['prix_unitaire'] : null;

            // Sécurité : la quantité confirmée ne peut pas dépasser la quantité demandée
            $qteConfirmee = isset($ligne['quantite_confirmee']) ? (int) $ligne['quantite_confirmee'] : null;
            if ($qteConfirmee !== null) {
                $qteConfirmee = max(1, min($qteConfirmee, $qteDemandee));
            }

            $qteStockee = in_array($status, ['disponible', 'partiel']) && $qteConfirmee !== null
                ? $qteConfirmee
                : null;

            $venteLibre = filter_var($ligne['vente_libre'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $pivotData = [
                'status' => $status,
                'quantite_confirmee' => $qteStockee,
                'vente_libre' => $venteLibre,
            ];
            if ($prixUnitaire !== null) {
                $pivotData['prix_unitaire'] = $prixUnitaire;
            }

            $commande->produits()->updateExistingPivot($produitId, $pivotData);

            $qteEffective = $status === 'indisponible' ? 0 : ($qteConfirmee ?? 1);
            if ($qteEffective > 0) {
                $nbDispo++;
            } else {
                $nbIndispo++;
            }
        }

        $commentairePharmacie = trim($request->input('commentaire', ''));

        $commande->load('produits');
        $montants = Commande::computeMontantsFromProduits($commande->produits);
        $commande->load('montantLivraison');
        $liv = (float) ($commande->montantLivraison?->designation ?? 0);

        $commande->update([
            'status' => 'en_attente',
            'status_pharmacie' => $nbDispo === 0 ? 'indisponible' : 'attente_confirmation',
            'pharmacie_refusee_id' => $nbDispo === 0 ? $pharmacieId : null,
            'prix_medicaments' => $montants['prix_medicaments'],
            'prix_parapharma' => $montants['prix_parapharma'],
            'prix_total' => $montants['prix_lignes'] + $liv,
            'dispo_pharmacie_at' => now(),
            ...($commentairePharmacie !== '' ? ['commentaire_pharmacie' => $commentairePharmacie] : []),
        ]);

        return back()->with('status', 'Disponibilité envoyée.');
    }

    /**
     * Le vendeur confirme le retrait / la remise au livreur.
     */
    public function validerRetrait(Request $request, Commande $commande)
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (! $pharmacieId || $commande->pharmacie_id != $pharmacieId) {
            abort(403);
        }
        if ($commande->status_pharmacie !== 'valide_a_preparer') {
            return back()->with('error', 'Seules les commandes validées peuvent être remises au livreur.');
        }

        // Seul status_pharmacie change — c'est l'admin qui passera status à 'retiree' quand le livreur confirmera
        $commande->update([
            'status_pharmacie' => 'livre',
        ]);

        return back()->with('status', 'Retrait validé.');
    }
}
