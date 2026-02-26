<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\MontantLivraison;
use App\Models\Pharmacie;
use App\Models\Produit;
use App\Services\PharmacieProximiteService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function __construct(
        private PharmacieProximiteService $pharmacieService
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Agent/Index', [
            'commandes' => Commande::with(['client', 'pharmacie', 'produits'])
                ->latest('created_at')
                ->paginate(15),
        ]);
    }

    public function nouvelleCommande(Request $request): Response
    {
        return Inertia::render('Agent/NouvelleCommande', [
            'pharmacies' => Pharmacie::with('zone')->get(),
            'produits' => Produit::all(),
            'modesPaiement' => ModePaiement::all(),
            'montantsLivraison' => MontantLivraison::all(),
            'livreurs' => Livreur::all(),
        ]);
    }

    public function rechercherPharmacie(Request $request)
    {
        $adresse = $request->input('adresse', '');
        $pharmacies = $this->pharmacieService->trouverPharmaciesProches($adresse);

        return response()->json(['pharmacies' => $pharmacies]);
    }

    public function rechercherClient(Request $request)
    {
        $search = $request->input('q', '');
        $clients = Client::where('tel', 'like', "%{$search}%")
            ->orWhere('nom', 'like', "%{$search}%")
            ->orWhere('prenom', 'like', "%{$search}%")
            ->limit(10)
            ->get();

        return response()->json(['clients' => $clients]);
    }

    public function rechercherProduit(Request $request)
    {
        $search = $request->input('q', '');
        $produits = Produit::where('designation', 'like', "%{$search}%")
            ->orWhere('dosage', 'like', "%{$search}%")
            ->limit(15)
            ->get();

        return response()->json(['produits' => $produits]);
    }

    public function storeCommande(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'client_nouveau' => 'nullable|array',
            'client_nouveau.nom' => 'required_with:client_nouveau|string',
            'client_nouveau.prenom' => 'required_with:client_nouveau|string',
            'client_nouveau.tel' => 'required_with:client_nouveau|string',
            'client_nouveau.adresse' => 'required_with:client_nouveau|string',
            'pharmacie_id' => 'required|exists:pharmacies,id',
            'produits' => 'required|array|min:1',
            'produits.*.produit_id' => 'required|exists:produits,id',
            'produits.*.quantite' => 'required|integer|min:1',
            'mode_paiement_id' => 'nullable|exists:modes_paiement,id',
            'montant_livraison_id' => 'nullable|exists:montants_livraison,id',
            'livreur_id' => 'nullable|exists:livreurs,id',
            'commentaire' => 'nullable|string',
        ]);

        $client = $validated['client_id']
            ? Client::findOrFail($validated['client_id'])
            : Client::create($validated['client_nouveau']);

        $numero = 'BDK' . now()->format('ymdHis') . rand(100, 999);

        $commande = Commande::create([
            'numero' => $numero,
            'client_id' => $client->id,
            'pharmacie_id' => $validated['pharmacie_id'],
            'mode_paiement_id' => $validated['mode_paiement_id'] ?? null,
            'livreur_id' => $validated['livreur_id'] ?? null,
            'montant_livraison_id' => $validated['montant_livraison_id'] ?? null,
            'date' => now(),
            'heurs' => now()->format('H:i'),
            'commentaire' => $validated['commentaire'] ?? null,
            'status' => 'nouvelle',
        ]);

        $prixTotal = 0;
        foreach ($validated['produits'] as $p) {
            $produit = Produit::findOrFail($p['produit_id']);
            $prixUnitaire = $produit->pu;
            $quantite = $p['quantite'];
            $commande->produits()->attach($produit->id, [
                'quantite' => $quantite,
                'prix_unitaire' => $prixUnitaire,
                'status' => 'disponible',
            ]);
            $prixTotal += $prixUnitaire * $quantite;
        }

        $commande->update(['prix_total' => $prixTotal]);

        return redirect()->route('agent.index')->with('success', "Commande {$commande->numero} créée.");
    }
}
