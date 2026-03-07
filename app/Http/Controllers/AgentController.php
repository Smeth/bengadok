<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\MontantLivraison;
use App\Models\Ordonnance;
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
            'modesPaiement' => ModePaiement::all(),
            'montantsLivraison' => MontantLivraison::all(),
            'livreurs' => Livreur::all(),
        ]);
    }

    public function rechercherPharmacie(Request $request)
    {
        $adresse = $request->input('adresse', '');
        $exclurePharmacieId = $request->integer('exclure_pharmacie_id', 0) ?: null;
        $pharmacies = $this->pharmacieService->trouverPharmaciesProches($adresse, $exclurePharmacieId);

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
        $produitsInput = $request->input('produits');
        if (is_string($produitsInput)) {
            $produitsDecoded = json_decode($produitsInput, true);
            $request->merge(['produits' => is_array($produitsDecoded) ? $produitsDecoded : []]);
        }
        $clientNouveauInput = $request->input('client_nouveau');
        if (is_string($clientNouveauInput)) {
            $decoded = json_decode($clientNouveauInput, true);
            if (is_array($decoded)) {
                $request->merge(['client_nouveau' => $decoded]);
            }
        }

        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'client_nouveau' => 'nullable|array',
            'client_nouveau.nom' => 'required_with:client_nouveau|string',
            'client_nouveau.prenom' => 'nullable|string|max:100',
            'client_nouveau.tel' => 'required_with:client_nouveau|string',
            'client_nouveau.adresse' => 'required_with:client_nouveau|string',
            'pharmacie_id' => 'required|exists:pharmacies,id',
            'produits' => 'required|array|min:1',
            'produits.*.designation' => 'required|string|max:255',
            'produits.*.dosage' => 'nullable|string|max:50',
            'produits.*.quantite' => 'required|integer|min:1',
            'produits.*.prix_unitaire' => 'required|numeric|min:0',
            'ordonnance' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
            'mode_paiement_id' => 'nullable|exists:modes_paiement,id',
            'montant_livraison_id' => 'nullable|exists:montants_livraison,id',
            'livreur_id' => 'nullable|exists:livreurs,id',
            'commentaire' => 'nullable|string',
        ]);

        $client = $validated['client_id']
            ? Client::findOrFail($validated['client_id'])
            : Client::create([
                'nom' => $validated['client_nouveau']['nom'],
                'prenom' => !empty(trim($validated['client_nouveau']['prenom'] ?? '')) ? trim($validated['client_nouveau']['prenom']) : null,
                'tel' => $validated['client_nouveau']['tel'],
                'adresse' => $validated['client_nouveau']['adresse'],
            ]);

        $ordonnanceId = null;
        if ($request->hasFile('ordonnance')) {
            $file = $request->file('ordonnance');
            $ext = $file->getClientOriginalExtension();
            $path = $file->storeAs('ordonnances/' . now()->format('Y-m'), uniqid() . '.' . $ext, 'public');
            $ordonnance = Ordonnance::create(['urlfile' => $path]);
            $ordonnanceId = $ordonnance->id;
        }

        $numero = 'BDK' . now()->format('ymdHis') . rand(100, 999);

        $commande = Commande::create([
            'numero' => $numero,
            'client_id' => $client->id,
            'pharmacie_id' => $validated['pharmacie_id'],
            'ordonnance_id' => $ordonnanceId,
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
            $produit = Produit::firstOrCreate(
                [
                    'designation' => trim($p['designation']),
                    'dosage' => trim($p['dosage'] ?? '') ?: null,
                ],
                [
                    'pu' => (float) $p['prix_unitaire'],
                    'forme' => null,
                    'type' => 'Vente libre',
                ]
            );
            $quantite = (int) $p['quantite'];
            $prixUnitaire = (float) $p['prix_unitaire'];
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

    /**
     * Renvoyer une commande (indisponible ou partielle) vers la 2ème pharmacie la plus proche.
     */
    public function renvoyerPharmacie(Request $request, Commande $commande)
    {
        if (!in_array($commande->status, ['en_attente', 'indisponible_pharmacie', 'partiellement_validee'])) {
            return back()->with('error', 'Cette commande ne peut pas être renvoyée.');
        }

        $client = $commande->client;
        $adresse = $client?->adresse ?? '';
        if (!$adresse) {
            return back()->with('error', 'Adresse client manquante.');
        }

        $pharmacieRefuseeId = $commande->pharmacie_id;
        $pharmacies = $this->pharmacieService->trouverPharmaciesProches($adresse, $pharmacieRefuseeId);
        $pharmacieSuivante = $pharmacies->first();
        if (!$pharmacieSuivante) {
            return back()->with('error', 'Aucune autre pharmacie proche disponible.');
        }

        $commande->update([
            'pharmacie_id' => $pharmacieSuivante->id,
            'pharmacie_refusee_id' => $pharmacieRefuseeId,
            'status' => 'nouvelle',
        ]);

        foreach ($commande->produits as $p) {
            $commande->produits()->updateExistingPivot($p->id, [
                'status' => 'disponible',
                'quantite_confirmee' => null,
            ]);
        }

        return back()->with('success', "Commande renvoyée à {$pharmacieSuivante->designation}.");
    }
}
