<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Commande;
use App\Models\MontantLivraison;
use App\Models\Ordonnance;
use App\Models\Pharmacie;
use App\Models\Produit;
use App\Models\ModePaiement;
use App\Models\Zone;
use App\Services\PharmacieProximiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommandeController extends Controller
{
    public function __construct(
        private PharmacieProximiteService $pharmacieService
    ) {}

    /**
     * Recherche la pharmacie la plus proche selon l'adresse (pour admin/agent).
     */
    public function rechercherPharmacieProche(Request $request)
    {
        $adresse = $request->input('adresse', '');
        $exclurePharmacieId = $request->integer('exclure_pharmacie_id', 0) ?: null;
        $pharmacies = $this->pharmacieService->trouverPharmaciesProches($adresse, $exclurePharmacieId);

        return response()->json(['pharmacies' => $pharmacies]);
    }
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }
        $allowedRoles = ['admin', 'super_admin', 'agent_call_center', 'gerant', 'vendeur'];
        if (!$user->hasAnyRole($allowedRoles)) {
            abort(403, 'Accès non autorisé à cette section.');
        }

        $query = Commande::with(['client', 'pharmacie', 'produits', 'livreur', 'modePaiement', 'montantLivraison', 'ordonnance'])
            ->when($request->user()?->pharmacie_id, fn ($q) => $q->where('pharmacie_id', $request->user()->pharmacie_id));

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%")
                        ->orWhere('tel', 'like', "%{$search}%"))
                    ->orWhereHas('produits', fn ($q) => $q->where('designation', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'validee') {
            $query->whereIn('status', ['validee', 'a_preparer']);
        } elseif ($status) {
            $query->where('status', $status);
        }
        }

        if ($periode = $request->input('periode')) {
            match ($periode) {
                'aujourdhui' => $query->whereDate('date', today()),
                'semaine' => $query->where('date', '>=', now()->startOfWeek()),
                'mois' => $query->where('date', '>=', now()->startOfMonth()),
                default => null,
            };
        }

        if ($date = $request->input('date')) {
            $query->whereDate('date', $date);
        }

        $commandes = $query->latest('date')->latest('created_at')->paginate(15)->withQueryString();

        $base = Commande::query()->when($request->user()?->pharmacie_id, fn ($q) => $q->where('pharmacie_id', $request->user()->pharmacie_id));
        $stats = [
            'nouvelles' => (clone $base)->where('status', 'nouvelle')->count(),
            'en_attente' => (clone $base)->where('status', 'en_attente')->count(),
            'validees' => (clone $base)->whereIn('status', ['validee', 'a_preparer'])->count(),
            'retirees' => (clone $base)->where('status', 'retiree')->count(),
            'livrees' => (clone $base)->where('status', 'livree')->count(),
            'annulees' => (clone $base)->where('status', 'annulee')->count(),
        ];

        $pharmacies = Pharmacie::with('zone')->get();
        $zones = Zone::withCount('pharmacies')->get();
        $produits = Produit::all();
        $modesPaiement = ModePaiement::all();
        $montantsLivraison = MontantLivraison::all();

        return Inertia::render('Commandes/Index', [
            'commandes' => $commandes,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'periode', 'date']),
            'pharmacies' => $pharmacies,
            'zones' => $zones,
            'produits' => $produits,
            'modesPaiement' => $modesPaiement,
            'montantsLivraison' => $montantsLivraison,
        ]);
    }

    public function show(Request $request, Commande $commande)
    {
        $user = $request->user();
        if ($user?->pharmacie_id && !$user->hasAnyRole(['admin', 'super_admin'])) {
            abort_unless($commande->pharmacie_id === $user->pharmacie_id, 403);
        }

        $commande->load(['client', 'pharmacie', 'pharmacieRefusee', 'produits', 'ordonnance', 'modePaiement', 'livreur', 'montantLivraison']);

        // Requête fetch/AJAX (modal) : retourner JSON. Requête Inertia (navigation) : retourner la page.
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['commande' => $commande]);
        }

        return Inertia::render('Commandes/Show', ['commande' => $commande]);
    }

    public function bulkAnnuler(Request $request): RedirectResponse
    {
        if (!$request->user()?->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])) {
            abort(403, 'Seuls les admins et l\'agent call-center peuvent annuler des commandes.');
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:commandes,id',
            'motif_annulation' => 'required|string|in:medicaments_indisponibles,demande_patient,erreur_commande,probleme_paiement',
        ]);

        $query = Commande::whereIn('id', $validated['ids']);
        if ($request->user()?->pharmacie_id && !$request->user()?->hasAnyRole(['admin', 'super_admin'])) {
            $query->where('pharmacie_id', $request->user()->pharmacie_id);
        }

        $count = $query->whereNotIn('status', ['annulee', 'livree', 'retiree'])->update([
            'status' => 'annulee',
            'motif_annulation' => $validated['motif_annulation'],
        ]);

        return back()->with('status', "{$count} commande(s) annulée(s).");
    }

    public function store(Request $request): RedirectResponse
    {
        if (!$request->user()?->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])) {
            abort(403, 'Seuls les admins et l\'agent call-center peuvent créer des commandes.');
        }

        $produitsInput = $request->input('produits');
        if (is_string($produitsInput)) {
            $produitsDecoded = json_decode($produitsInput, true);
            $request->merge(['produits' => is_array($produitsDecoded) ? $produitsDecoded : []]);
        }

        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'client_nom' => 'required_without:client_id|string|max:100',
            'client_prenom' => 'nullable|string|max:100',
            'client_tel' => 'required_without:client_id|string|max:20',
            'client_adresse' => 'required_without:client_id|string',
            'pharmacie_id' => 'required|exists:pharmacies,id',
            'beneficiaire' => 'nullable|string|max:100',
            'produits' => 'required|array|min:1',
            'produits.*.designation' => 'required|string|max:255',
            'produits.*.dosage' => 'nullable|string|max:50',
            'produits.*.quantite' => 'required|integer|min:1',
            'produits.*.prix_unitaire' => 'required|numeric|min:0',
            'ordonnance' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
            'mode_paiement_id' => 'nullable|exists:modes_paiement,id',
            'montant_livraison_id' => 'nullable|exists:montants_livraison,id',
            'commentaire' => 'nullable|string',
        ]);

        $clientId = $validated['client_id'] ?? null;
        $prenom = isset($validated['client_prenom']) ? trim($validated['client_prenom']) : null;
        $client = $clientId
            ? Client::findOrFail($clientId)
            : Client::create([
                'nom' => $validated['client_nom'],
                'prenom' => $prenom ?: null,
                'tel' => $validated['client_tel'],
                'adresse' => $validated['client_adresse'],
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
            'montant_livraison_id' => $validated['montant_livraison_id'] ?? null,
            'date' => now(),
            'heurs' => now()->format('H:i'),
            'commentaire' => $validated['commentaire'] ?? null,
            'beneficiaire' => $validated['beneficiaire'] ?? null,
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

        $montantLivraison = $validated['montant_livraison_id'] ? (MontantLivraison::find($validated['montant_livraison_id'])?->designation ?? 0) : 0;
        $commande->update(['prix_total' => $prixTotal + (float) $montantLivraison]);

        return redirect()->route('commandes.index')->with('status', "Commande {$commande->numero} créée.");
    }

    public function updateStatus(Request $request, Commande $commande): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:nouvelle,en_attente,validee,partiellement_validee,indisponible_pharmacie,a_preparer,retiree,livree,annulee',
            'motif_annulation' => 'nullable|string|in:medicaments_indisponibles,demande_patient,erreur_commande,probleme_paiement',
        ]);

        if ($validated['status'] === 'validee' && $commande->status === 'en_attente' && !$commande->acceptation_client) {
            return back()->with('error', 'Le client doit avoir validé le coût total avant de valider la commande.');
        }

        $commande->update([
            'status' => $validated['status'],
            'motif_annulation' => $validated['status'] === 'annulee' ? ($validated['motif_annulation'] ?? null) : null,
        ]);

        return back();
    }

    public function setAcceptationClient(Request $request, Commande $commande): RedirectResponse
    {
        $validated = $request->validate([
            'acceptation_client' => 'required|boolean',
        ]);

        $commande->update(['acceptation_client' => $validated['acceptation_client']]);
        return back();
    }

    public function setMontantLivraison(Request $request, Commande $commande): RedirectResponse
    {
        $validated = $request->validate([
            'montant_livraison_id' => 'required|exists:montants_livraison,id',
        ]);

        $montant = MontantLivraison::findOrFail($validated['montant_livraison_id']);
        $sousTotal = $commande->produits->sum(fn ($p) => $p->pivot->quantite * (float) $p->pivot->prix_unitaire);
        $nouveauTotal = $sousTotal + (float) $montant->designation;

        $commande->update([
            'montant_livraison_id' => $validated['montant_livraison_id'],
            'prix_total' => $nouveauTotal,
        ]);

        return back();
    }
}
