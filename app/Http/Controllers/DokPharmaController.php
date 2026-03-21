<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DokPharmaController extends Controller
{
    /**
     * Dashboard pharmacie : stats, graphiques, meilleures ventes.
     */
    public function dashboard(Request $request): Response
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (!$pharmacieId) {
            return $this->emptyDashboard($request);
        }

        $now = now();
        $debutSemaine = $now->copy()->startOfWeek();
        $debutMois = $now->copy()->startOfMonth();

        // Revenu total (commandes livrées ou validées)
        $revenuTotal = (float) Commande::where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', ['livre', 'valide_a_preparer', 'attente_confirmation'])
            ->sum('prix_total');

        // Revenu semaine précédente pour % évolution
        $revenuSemainePrec = (float) Commande::where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', ['livre', 'valide_a_preparer', 'attente_confirmation'])
            ->whereBetween('created_at', [$debutSemaine->copy()->subWeek(), $debutSemaine])
            ->sum('prix_total');
        $revenuSemaineActuelle = (float) Commande::where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', ['livre', 'valide_a_preparer', 'attente_confirmation'])
            ->where('created_at', '>=', $debutSemaine)
            ->sum('prix_total');
        $pctRevenu = $revenuSemainePrec > 0
            ? round((($revenuSemaineActuelle - $revenuSemainePrec) / $revenuSemainePrec) * 100)
            : 0;

        // Nombre total commandes
        $nbCommandes = Commande::where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', ['livre', 'valide_a_preparer', 'attente_confirmation', 'nouvelle'])
            ->count();
        $nbCmdSemainePrec = Commande::where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', ['livre', 'valide_a_preparer', 'attente_confirmation', 'nouvelle'])
            ->whereBetween('created_at', [$debutSemaine->copy()->subWeek(), $debutSemaine])
            ->count();
        $nbCmdSemaineActuelle = Commande::where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', ['livre', 'valide_a_preparer', 'attente_confirmation', 'nouvelle'])
            ->where('created_at', '>=', $debutSemaine)
            ->count();
        $pctCmd = $nbCmdSemainePrec > 0
            ? round((($nbCmdSemaineActuelle - $nbCmdSemainePrec) / $nbCmdSemainePrec) * 100)
            : 0;

        // Clients uniques
        $nbClients = Commande::where('pharmacie_id', $pharmacieId)
            ->whereNotNull('client_id')
            ->distinct('client_id')
            ->count('client_id');
        $nbClientsSemainePrec = Commande::where('pharmacie_id', $pharmacieId)
            ->whereNotNull('client_id')
            ->whereBetween('created_at', [$debutSemaine->copy()->subWeek(), $debutSemaine])
            ->distinct('client_id')
            ->count('client_id');
        $nbClientsSemaineActuelle = Commande::where('pharmacie_id', $pharmacieId)
            ->whereNotNull('client_id')
            ->where('created_at', '>=', $debutSemaine)
            ->distinct('client_id')
            ->count('client_id');
        $pctClients = $nbClientsSemainePrec > 0
            ? round((($nbClientsSemaineActuelle - $nbClientsSemainePrec) / $nbClientsSemainePrec) * 100)
            : 0;

        // Revenus par jour (12 derniers jours du mois)
        $revenusParJour = Commande::where('pharmacie_id', $pharmacieId)
            ->whereIn('status_pharmacie', ['livre', 'valide_a_preparer', 'attente_confirmation'])
            ->where('created_at', '>=', $debutMois)
            ->select(DB::raw('DATE(created_at) as jour'), DB::raw('SUM(prix_total) as total'))
            ->groupBy('jour')
            ->orderBy('jour')
            ->get()
            ->keyBy('jour');

        $joursMois = collect(range(1, min(12, $now->day)))->map(function ($d) use ($debutMois, $revenusParJour) {
            $date = $debutMois->copy()->addDays($d - 1);
            $key = $date->format('Y-m-d');
            return [
                'label' => str_pad((string) $d, 2, '0', STR_PAD_LEFT),
                'valeur' => (float) ($revenusParJour->get($key)?->total ?? 0),
            ];
        });

        // Volume commandes par jour (7 derniers jours de la semaine)
        $volumeParJour = Commande::where('pharmacie_id', $pharmacieId)
            ->where('created_at', '>=', $debutSemaine)
            ->select(DB::raw('DATE(created_at) as jour'), DB::raw('COUNT(*) as nb'))
            ->groupBy('jour')
            ->get()
            ->keyBy('jour');

        $volumeSemaine = collect(range(0, 6))->map(function ($offset) use ($debutSemaine, $volumeParJour) {
            $date = $debutSemaine->copy()->addDays($offset);
            $key = $date->format('Y-m-d');
            return [
                'label' => str_pad((string) ($offset + 1), 2, '0', STR_PAD_LEFT),
                'valeur' => (int) ($volumeParJour->get($key)?->nb ?? 0),
            ];
        });

        // Meilleures ventes (produits les plus vendus ce mois)
        $meilleursVentes = DB::table('commande_produit')
            ->join('commandes', 'commande_produit.commande_id', '=', 'commandes.id')
            ->join('produits', 'commande_produit.produit_id', '=', 'produits.id')
            ->where('commandes.pharmacie_id', $pharmacieId)
            ->whereIn('commandes.status_pharmacie', ['livre', 'valide_a_preparer', 'attente_confirmation'])
            ->where('commandes.created_at', '>=', $debutMois)
            ->whereRaw("(commande_produit.status IS NULL OR commande_produit.status <> 'indisponible')")
            ->select(
                'produits.id',
                'produits.designation',
                DB::raw('SUM(COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)) as qte'),
                DB::raw('SUM(commande_produit.prix_unitaire * COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)) as ca')
            )
            ->groupBy('produits.id', 'produits.designation')
            ->orderByDesc('ca')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'designation' => $r->designation,
                'ca' => (float) $r->ca,
            ])
            ->values()
            ->all();

        return Inertia::render('DokPharma/Dashboard', [
            'stats' => [
                'revenu_total' => round($revenuTotal, 0),
                'pct_revenu' => $pctRevenu,
                'nb_commandes' => $nbCommandes,
                'pct_commandes' => $pctCmd,
                'nb_clients' => $nbClients,
                'pct_clients' => $pctClients,
            ],
            'revenusParJour' => $joursMois->values()->all(),
            'volumeParJour' => $volumeSemaine->values()->all(),
            'meilleursVentes' => $meilleursVentes,
        ]);
    }

    private function emptyDashboard(Request $request): Response
    {
        return Inertia::render('DokPharma/Dashboard', [
            'stats' => [
                'revenu_total' => 0,
                'pct_revenu' => 0,
                'nb_commandes' => 0,
                'pct_commandes' => 0,
                'nb_clients' => 0,
                'pct_clients' => 0,
            ],
            'revenusParJour' => [],
            'volumeParJour' => [],
            'meilleursVentes' => [],
        ]);
    }

    public function index(Request $request): Response
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (!$pharmacieId) {
            return Inertia::render('DokPharma/Index', [
                'commandes' => ['data' => [], 'links' => [], 'current_page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0, 'total' => 0],
                'stats'     => ['nouvelles' => 0, 'en_attente' => 0, 'a_preparer' => 0, 'livrees' => 0],
                'onglet'    => $request->input('onglet', 'nouvelles'),
            ]);
        }

        $onglet = $request->input('onglet', 'nouvelles');

        $query = Commande::with(['client', 'produits', 'ordonnance'])
            ->where('pharmacie_id', $pharmacieId);

        $query
            ->when($onglet === 'nouvelles',   fn ($q) => $q->where('status_pharmacie', 'nouvelle'))
            ->when($onglet === 'en_attente',  fn ($q) => $q->whereIn('status_pharmacie', ['attente_confirmation', 'indisponible']))
            ->when($onglet === 'a_preparer',  fn ($q) => $q->where('status_pharmacie', 'valide_a_preparer'))
            ->when($onglet === 'livrees',     fn ($q) => $q->where('status_pharmacie', 'livre'))
            ->when(!in_array($onglet, ['nouvelles', 'en_attente', 'a_preparer', 'livrees']),
                fn ($q) => $q->where('status_pharmacie', 'nouvelle'));

        $commandes = $query
            ->latest('date')
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($c) {
                return [
                    'id'               => $c->id,
                    'numero'           => $c->numero,
                    'date'             => $c->date
                        ? \Carbon\Carbon::parse($c->date)->format('d/m/Y H:i')
                        : ($c->created_at ? $c->created_at->format('d/m/Y H:i') : ''),
                    'status'           => $c->status,
                    'status_pharmacie' => $c->status_pharmacie,
                    'client'           => $c->client
                        ? ['nom' => $c->client->nom, 'prenom' => $c->client->prenom]
                        : null,
                    'produits'         => $c->produits->map(fn ($p) => [
                        'id'          => $p->id,
                        'designation' => $p->designation,
                        'pivot'       => [
                            'quantite'           => $p->pivot->quantite,
                            'prix_unitaire'      => (float) ($p->pivot->prix_unitaire ?? 0),
                            'status'             => $p->pivot->status ?? 'disponible',
                            'quantite_confirmee' => $p->pivot->quantite_confirmee ?? null,
                        ],
                    ])->values(),
                    'ordonnance_id'  => $c->ordonnance_id,
                    'ordonnance_url' => $c->ordonnance?->urlfile
                        ? asset('storage/' . ltrim($c->ordonnance->urlfile, '/'))
                        : null,
                    'commentaire'    => $c->commentaire,
                    'prix_total'     => (float) ($c->prix_total ?? 0),
                ];
            });

        $stats = [
            'nouvelles'  => Commande::where('pharmacie_id', $pharmacieId)->where('status_pharmacie', 'nouvelle')->count(),
            'en_attente' => Commande::where('pharmacie_id', $pharmacieId)->whereIn('status_pharmacie', ['attente_confirmation', 'indisponible'])->count(),
            'a_preparer' => Commande::where('pharmacie_id', $pharmacieId)->where('status_pharmacie', 'valide_a_preparer')->count(),
            'livrees'    => Commande::where('pharmacie_id', $pharmacieId)->where('status_pharmacie', 'livre')->count(),
        ];

        return Inertia::render('DokPharma/Index', [
            'commandes' => $commandes,
            'stats'     => $stats,
            'onglet'    => $onglet,
        ]);
    }

    /**
     * La pharmacie valide la disponibilité + prix des médicaments.
     */
    public function validerDisponibilite(Request $request, Commande $commande)
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (!$pharmacieId || $commande->pharmacie_id != $pharmacieId) {
            abort(403);
        }

        // Charger les produits pour connaître les quantités demandées
        $commande->load('produits');
        $produitMap = $commande->produits->keyBy('id');

        $lignes    = $request->input('lignes', []);
        $nbDispo   = 0;
        $nbIndispo = 0;

        // Vérification préalable : tout produit disponible doit avoir un prix > 0
        foreach ($lignes as $ligne) {
            $status       = $ligne['status'] ?? 'disponible';
            $prixUnitaire = isset($ligne['prix_unitaire']) ? (float) $ligne['prix_unitaire'] : 0;
            if (in_array($status, ['disponible', 'partiel']) && $prixUnitaire <= 0) {
                return back()->with('error', 'Veuillez saisir le prix pour tous les médicaments disponibles avant d\'envoyer.');
            }
        }

        foreach ($lignes as $ligne) {
            $produitId    = (int) ($ligne['produit_id'] ?? 0);
            $produit      = $produitMap->get($produitId);
            if (!$produit) continue;

            $qteDemandee  = (int) $produit->pivot->quantite;
            $status       = $ligne['status'] ?? 'disponible';
            $prixUnitaire = isset($ligne['prix_unitaire']) ? (float) $ligne['prix_unitaire'] : null;

            // Sécurité : la quantité confirmée ne peut pas dépasser la quantité demandée
            $qteConfirmee = isset($ligne['quantite_confirmee']) ? (int) $ligne['quantite_confirmee'] : null;
            if ($qteConfirmee !== null) {
                $qteConfirmee = max(1, min($qteConfirmee, $qteDemandee));
            }

            $qteStockee = in_array($status, ['disponible', 'partiel']) && $qteConfirmee !== null
                ? $qteConfirmee
                : null;

            $pivotData = ['status' => $status, 'quantite_confirmee' => $qteStockee];
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

        // Recalcul prix total depuis le pivot mis à jour
        $commande->load('produits'); // recharge pour avoir les nouvelles valeurs
        $prixTotal = $commande->produits->sum(function ($p) {
            if ($p->pivot->status === 'indisponible') return 0;
            $qte = $p->pivot->quantite_confirmee ?? $p->pivot->quantite;
            return $qte * (float) $p->pivot->prix_unitaire;
        });

        $commentairePharmacie = trim($request->input('commentaire', ''));

        if ($nbDispo === 0) {
            $commande->update([
                'status'                => 'en_attente',
                'status_pharmacie'      => 'indisponible',
                'pharmacie_refusee_id'  => $pharmacieId,
                ...($commentairePharmacie !== '' ? ['commentaire' => $commentairePharmacie] : []),
            ]);
        } else {
            $commande->update([
                'status'           => 'en_attente',
                'status_pharmacie' => 'attente_confirmation',
                'prix_total'       => $prixTotal,
                ...($commentairePharmacie !== '' ? ['commentaire' => $commentairePharmacie] : []),
            ]);
        }

        return back()->with('status', 'Disponibilité envoyée.');
    }

    /**
     * Le vendeur confirme le retrait / la remise au livreur.
     */
    public function validerRetrait(Request $request, Commande $commande)
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (!$pharmacieId || $commande->pharmacie_id != $pharmacieId) {
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
