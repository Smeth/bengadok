<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommandeRequest;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\Pharmacie;
use App\Models\Produit;
use App\Services\CommandeMontantCalculator;
use App\Services\CommandeService;
use App\Services\PharmacieProximiteService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function __construct(
        private PharmacieProximiteService $pharmacieService,
        private CommandeService $commandeService
    ) {}

    public function nouvelleCommande(Request $request): Response
    {
        return Inertia::render('Agent/NouvelleCommande', [
            'pharmacies' => Pharmacie::with(['zone', 'typePharmacie', 'heurs'])->get(),
            'modesPaiement' => ModePaiement::all(),
            'livreurs' => Livreur::all(),
            'arrondissements' => Client::ARRONDISSEMENTS,
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

    public function storeCommande(StoreCommandeRequest $request)
    {
        try {
            $data = $request->getDataForService();
            $data['livreur_id'] = $data['livreur_id'] ?? null;
            $commande = $this->commandeService->create($data, $request->file('ordonnance'));
        } catch (\RuntimeException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()->route('commandes.index')->with('success', "Commande {$commande->numero} créée.");
    }

    /**
     * Renvoyer toute la commande vers une 2ème pharmacie (tout indisponible).
     */
    public function renvoyerPharmacie(Request $request, Commande $commande)
    {
        if ($commande->status !== 'en_attente') {
            return back()->with('error', 'Cette commande ne peut pas être renvoyée.');
        }
        if ($commande->parent_id) {
            return back()->with('error', 'Une commande secondaire ne peut pas être renvoyée.');
        }

        $client = $commande->client;
        $adresse = $client?->adresse ?? '';
        if (! $adresse) {
            return back()->with('error', 'Adresse client manquante.');
        }

        $pharmacieRefuseeId = $commande->pharmacie_id;
        $pharmacies = $this->pharmacieService->trouverPharmaciesProches($adresse, $pharmacieRefuseeId);
        $pharmacieSuivante = $pharmacies->first();
        if (! $pharmacieSuivante) {
            return back()->with('error', 'Aucune autre pharmacie proche disponible.');
        }

        foreach ($commande->produits as $p) {
            $commande->produits()->updateExistingPivot($p->id, [
                'status' => 'en_attente',
                'quantite_confirmee' => null,
            ]);
        }

        // Montants recalculés sur les lignes redevenues « en_attente » : évite d'afficher
        // les prix/statuts figés par l'ancienne pharmacie tant que la nouvelle n'a pas validé.
        $commande->load('produits');
        $montants = CommandeMontantCalculator::fromProduitsRelation($commande->produits, false);
        $liv = $commande->montantLivraisonClient();

        $commande->update([
            'pharmacie_id' => $pharmacieSuivante->id,
            'pharmacie_refusee_id' => $pharmacieRefuseeId,
            'status' => 'nouvelle',
            'status_pharmacie' => 'nouvelle',
            'prix_medicaments' => $montants['prix_medicaments'],
            'prix_parapharma' => $montants['prix_parapharma'],
            'prix_total' => $montants['prix_lignes'] + $liv,
        ]);

        return back()->with('success', "Commande renvoyée à {$pharmacieSuivante->designation}.");
    }

    /**
     * Renvoyer uniquement les produits manquants vers une 2ème pharmacie (renvoi partiel).
     */
    public function renvoyerPharmaciePartiel(Request $request, Commande $commande)
    {
        if ($commande->status !== 'en_attente') {
            return back()->with('error', 'Seules les commandes en attente peuvent être renvoyées partiellement.');
        }
        if ($commande->parent_id) {
            return back()->with('error', 'Une commande secondaire ne peut pas être renvoyée.');
        }

        $validated = $request->validate([
            'pharmacie_id' => 'required|exists:pharmacies,id',
            'lignes' => 'required|array|min:1',
            'lignes.*.produit_id' => 'required|exists:produits,id',
            'lignes.*.quantite' => 'required|integer|min:1',
        ]);

        $pharmacieId = (int) $validated['pharmacie_id'];
        if ($pharmacieId === $commande->pharmacie_id) {
            return back()->with('error', 'Sélectionnez une pharmacie différente.');
        }

        $commande->load('produits');
        $produitsEnfant = [];

        foreach ($validated['lignes'] as $ligne) {
            $produitId = (int) $ligne['produit_id'];
            $quantiteEnfant = (int) $ligne['quantite'];
            $produitLigne = $commande->produits->firstWhere('id', $produitId);
            $pivot = $produitLigne?->pivot;
            if (! $pivot) {
                continue;
            }
            $quantiteParent = $pivot->quantite;
            $quantiteConfirmee = (int) ($pivot->quantite_confirmee ?? 0);
            $prixUnitaire = (float) $pivot->prix_unitaire;
            // Le type est repris tel quel de la ligne d'origine (déjà figé ou, à défaut, catalogue courant).
            $typeLigne = $pivot->type ?? $produitLigne->type;

            if ($quantiteEnfant <= 0 || $quantiteEnfant > $quantiteParent - $quantiteConfirmee) {
                continue;
            }

            $nouvelleQuantiteParent = $quantiteParent - $quantiteEnfant;
            if ($nouvelleQuantiteParent <= 0) {
                $commande->produits()->detach($produitId);
            } else {
                $commande->produits()->updateExistingPivot($produitId, [
                    'quantite' => $nouvelleQuantiteParent,
                    'quantite_confirmee' => min($quantiteConfirmee, $nouvelleQuantiteParent),
                    'status' => $nouvelleQuantiteParent === $quantiteConfirmee ? 'disponible' : 'partiel',
                ]);
            }

            $produitsEnfant[] = [
                'produit_id' => $produitId,
                'quantite' => $quantiteEnfant,
                'prix_unitaire' => $prixUnitaire,
                'type' => $typeLigne,
            ];
        }

        if (empty($produitsEnfant)) {
            return back()->with('error', 'Aucun produit valide à renvoyer.');
        }

        $nbEnfants = $commande->enfants()->count();
        $numeroEnfant = $commande->numero.'-'.($nbEnfants + 1);

        $commandeEnfant = Commande::create([
            'numero' => $numeroEnfant,
            'client_id' => $commande->client_id,
            'pharmacie_id' => $pharmacieId,
            'parent_id' => $commande->id,
            'ordonnance_id' => $commande->ordonnance_id,
            'mode_paiement_id' => $commande->mode_paiement_id,
            'livreur_id' => $commande->livreur_id,
            'montant_livraison_id' => $commande->montant_livraison_id,
            'date' => $commande->date,
            'heurs' => $commande->heurs,
            'commentaire' => $commande->commentaire,
            'commentaire_pharmacie' => null,
            'status' => 'nouvelle',
            'status_pharmacie' => 'nouvelle',
        ]);

        foreach ($produitsEnfant as $p) {
            $commandeEnfant->produits()->attach($p['produit_id'], [
                'quantite' => $p['quantite'],
                'prix_unitaire' => $p['prix_unitaire'],
                'status' => 'en_attente',
                'type' => $p['type'],
            ]);
        }

        // Enfant : toutes les lignes viennent d'être attachées « en_attente » (rien encore
        // triagé par la nouvelle pharmacie) → somme brute, comme à la création d'une commande.
        $montantsEnfant = CommandeMontantCalculator::fromInputLines($produitsEnfant);
        $livEnfant = $commandeEnfant->montantLivraisonClient();
        $commandeEnfant->update([
            'prix_medicaments' => $montantsEnfant['prix_medicaments'],
            'prix_parapharma' => $montantsEnfant['prix_parapharma'],
            'prix_total' => $montantsEnfant['prix_lignes'] + $livEnfant,
        ]);

        // Parent : lignes déjà triagées par la pharmacie (disponible/partiel/indisponible)
        // → on exclut les indisponibles, comme partout ailleurs dans le calcul des montants.
        $commande->load('produits');
        $montantsParent = Commande::computeMontantsFromProduits($commande->produits);
        $livParent = $commande->montantLivraisonClient();
        $commande->update([
            'prix_medicaments' => $montantsParent['prix_medicaments'],
            'prix_parapharma' => $montantsParent['prix_parapharma'],
            'prix_total' => $montantsParent['prix_lignes'] + $livParent,
        ]);

        $pharmacie = Pharmacie::findOrFail($pharmacieId);

        return back()->with('success', "Produits manquants renvoyés à {$pharmacie->designation}.");
    }
}
