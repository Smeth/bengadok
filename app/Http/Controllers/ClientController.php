<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $zoneId = $request->input('zone_id', '');
        $tri = $request->input('tri', 'nom');
        $frequence = $request->input('frequence', '');

        $query = Client::with(['zone', 'commandes' => fn ($q) => $q->whereIn('status', ['validee', 'livree', 'a_preparer'])]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('tel', 'like', "%{$search}%")
                    ->orWhere('adresse', 'like', "%{$search}%");
            });
        }

        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        $clients = $query->get()->map(function ($c) {
            $cmdValides = $c->commandes->filter(fn ($cmd) => in_array($cmd->status, ['validee', 'livree', 'a_preparer']));
            $totalDepense = $cmdValides->sum('prix_total');
            $nbCommandes = $cmdValides->count();
            $panierMoyen = $nbCommandes > 0 ? round($totalDepense / $nbCommandes, 0) : 0;

            $medicamentsFrequents = DB::table('commande_produit')
                ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
                ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
                ->where('commandes.client_id', $c->id)
                ->whereIn('commandes.status', ['validee', 'livree', 'a_preparer'])
                ->select('produits.designation', DB::raw('SUM(commande_produit.quantite) as total'))
                ->groupBy('produits.id', 'produits.designation')
                ->orderByDesc('total')
                ->limit(3)
                ->get()
                ->pluck('designation')
                ->toArray();

            $habitué = $nbCommandes >= 5;

            return [
                'id' => $c->id,
                'nom' => $c->nom,
                'prenom' => $c->prenom,
                'tel' => $c->tel,
                'adresse' => $c->adresse,
                'zone' => $c->zone?->designation,
                'zone_id' => $c->zone_id,
                'nb_commandes' => $nbCommandes,
                'total_depense' => $totalDepense,
                'panier_moyen' => $panierMoyen,
                'medicaments_frequents' => $medicamentsFrequents,
                'habitué' => $habitué,
                'created_at' => $c->created_at?->timestamp,
            ];
        });

        if ($frequence === 'habitué') {
            $clients = $clients->filter(fn ($c) => $c['habitué'])->values();
        } elseif ($frequence === 'occasionnel') {
            $clients = $clients->filter(fn ($c) => !$c['habitué'])->values();
        }

        $clients = match ($tri) {
            'commandes' => $clients->sortByDesc('nb_commandes')->values(),
            'depense' => $clients->sortByDesc('total_depense')->values(),
            'recent' => $clients->sortByDesc(fn ($c) => $c['created_at'] ?? 0)->values(),
            default => $clients->sortBy(fn ($c) => $c['prenom'] . ' ' . $c['nom'])->values(),
        };

        $zones = Zone::orderBy('designation')->get(['id', 'designation']);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'zones' => $zones,
            'filters' => $request->only(['search', 'zone_id', 'tri', 'frequence']),
        ]);
    }

    public function show(Client $client): Response
    {
        $client->load('zone');
        $commandes = $client->commandes()->whereIn('status', ['validee', 'livree', 'a_preparer'])->with('produits')->get();

        $totalDepense = $commandes->sum('prix_total');
        $nbCommandes = $commandes->count();
        $panierMoyen = $nbCommandes > 0 ? round($totalDepense / $nbCommandes, 0) : 0;
        $derniereCommande = $commandes->sortByDesc('date')->first();
        $clientDepuis = $client->client_depuis ?? $client->created_at;

        $pourSoi = $commandes->filter(fn ($c) => empty($c->beneficiaire) || $c->beneficiaire === 'Soi-même')->count();
        $pourTiers = $commandes->filter(fn ($c) => !empty($c->beneficiaire) && $c->beneficiaire !== 'Soi-même')->count();
        $pctSoi = $nbCommandes > 0 ? round(($pourSoi / $nbCommandes) * 100) : 0;
        $pctTiers = $nbCommandes > 0 ? round(($pourTiers / $nbCommandes) * 100) : 0;

        $beneficiaires = $commandes->pluck('beneficiaire')->filter()->countBy();
        $tiersFrequent = $beneficiaires->sortDesc()->keys()->first() ?? '-';
        $categoriesTiers = $beneficiaires->keys()->unique()->filter(fn ($b) => $b && $b !== 'Soi-même')->values()->toArray();

        $medicamentsFrequents = DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
            ->where('commandes.client_id', $client->id)
            ->whereIn('commandes.status', ['validee', 'livree', 'a_preparer'])
            ->select('produits.designation', DB::raw('SUM(commande_produit.quantite) as total'))
            ->groupBy('produits.id', 'produits.designation')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->pluck('designation')
            ->toArray();

        return Inertia::render('Clients/Show', [
            'client' => [
                'id' => $client->id,
                'nom' => $client->nom,
                'prenom' => $client->prenom,
                'tel' => $client->tel,
                'tel_secondaire' => $client->tel_secondaire,
                'adresse' => $client->adresse,
                'zone' => $client->zone?->designation,
                'client_depuis' => $clientDepuis?->format('d/m/Y'),
                'derniere_commande' => $derniereCommande?->date?->format('d/m/Y'),
                'nb_commandes' => $nbCommandes,
                'total_depense' => $totalDepense,
                'panier_moyen' => $panierMoyen,
                'habitué' => $nbCommandes >= 5,
                'pour_soi' => $pourSoi,
                'pour_tiers' => $pourTiers,
                'pct_soi' => $pctSoi,
                'pct_tiers' => $pctTiers,
                'categories_tiers' => $categoriesTiers,
                'tiers_frequent' => $tiersFrequent,
                'medicaments_frequents' => $medicamentsFrequents,
            ],
        ]);
    }
}
