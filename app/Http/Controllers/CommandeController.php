<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommandeRequest;
use App\Models\Client;
use App\Models\Commande;
use App\Models\ModePaiement;
use App\Models\MontantLivraison;
use App\Models\Pharmacie;
use App\Models\Produit;
use App\Models\Zone;
use App\Services\CommandeService;
use App\Services\PharmacieProximiteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommandeController extends Controller
{
    public function __construct(
        private PharmacieProximiteService $pharmacieService,
        private CommandeService $commandeService
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
        if (! $user) {
            return redirect()->route('login');
        }
        $this->authorize('viewAny', Commande::class);

        $query = $this->commandesIndexBaseQuery($request)
            ->with(['client', 'pharmacie', 'produits', 'livreur', 'modePaiement', 'montantLivraison', 'ordonnance']);

        $this->applyCommandeIndexSearch($query, $request->input('search'));

        if ($status = $request->input('status')) {
            if ($status === 'validee') {
                $query->whereIn('status', ['validee', 'a_preparer']);
            } else {
                $query->where('status', $status);
            }
        }

        $this->applyCommandeIndexTemporalFilters($query, $request);

        $commandes = $query
            ->orderByRaw('COALESCE(commandes.date, DATE(commandes.created_at)) DESC')
            ->orderByDesc('commandes.created_at')
            ->paginate(15)
            ->withQueryString();

        $statsBase = $this->commandesIndexBaseQuery($request);
        $this->applyCommandeIndexSearch($statsBase, $request->input('search'));
        $this->applyCommandeIndexTemporalFilters($statsBase, $request);

        $stats = [
            'nouvelles' => (clone $statsBase)->where('status', 'nouvelle')->count(),
            'en_attente' => (clone $statsBase)->where('status', 'en_attente')->count(),
            'validees' => (clone $statsBase)->whereIn('status', ['validee', 'a_preparer'])->count(),
            'livrees' => (clone $statsBase)->where('status', 'retiree')->count(),
            'annulees' => (clone $statsBase)->where('status', 'annulee')->count(),
        ];

        $pharmacies = Pharmacie::with(['zone', 'typePharmacie', 'heurs'])->get();
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
        $this->authorize('view', $commande);
        $user = $request->user();

        $commande->load(['client', 'pharmacie', 'pharmacieRefusee', 'produits', 'ordonnance', 'modePaiement', 'livreur', 'montantLivraison', 'enfants.pharmacie', 'enfants.produits', 'parent']);

        $pharmacieOptions = [];
        if ($user?->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])
            && $commande->status === 'en_attente'
            && ! $commande->parent_id
            && $commande->client?->adresse) {
            $pharmacieOptions = $this->pharmacieService->trouverPharmaciesProches(
                $commande->client->adresse,
                $commande->pharmacie_id
            )->toArray();
        }

        // Requête fetch/AJAX (modal) : retourner JSON. Requête Inertia (navigation) : retourner la page.
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['commande' => $commande]);
        }

        return Inertia::render('Commandes/Show', [
            'commande' => $commande,
            'pharmacieOptions' => $pharmacieOptions,
        ]);
    }

    public function recu(Request $request, Commande $commande)
    {
        $this->authorize('view', $commande);
        if ($commande->status !== 'retiree') {
            return redirect()->route('commandes.show', $commande)
                ->with('error', 'Le reçu n\'est disponible que pour les commandes livrées.');
        }

        $commande->load(['client', 'pharmacie', 'produits', 'modePaiement', 'montantLivraison']);

        // Téléchargement PDF
        if ($request->boolean('download')) {
            $pdf = Pdf::loadView('recu', ['commande' => $commande, 'hideActions' => true]);
            $filename = 'recu-commande-'.$commande->numero.'.pdf';

            return $pdf->download($filename);
        }

        return view('recu', ['commande' => $commande]);
    }

    public function edit(Request $request, Commande $commande): Response
    {
        $this->authorize('update', $commande);
        if (! in_array($commande->status, ['nouvelle', 'en_attente'])) {
            return redirect()->route('commandes.show', $commande)
                ->with('error', 'Seules les commandes « nouvelle » ou « en attente » peuvent être modifiées.');
        }

        $commande->load(['client', 'pharmacie', 'produits', 'ordonnance', 'modePaiement', 'montantLivraison']);

        return Inertia::render('Commandes/Edit', [
            'commande' => $commande,
            'pharmacies' => Pharmacie::with('zone')->get(),
            'modesPaiement' => ModePaiement::all(),
            'montantsLivraison' => MontantLivraison::all(),
        ]);
    }

    public function update(Request $request, Commande $commande): RedirectResponse
    {
        $this->authorize('update', $commande);
        if (! in_array($commande->status, ['nouvelle', 'en_attente'])) {
            return back()->with('error', 'Seules les commandes « nouvelle » ou « en attente » peuvent être modifiées.');
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
            'date' => 'required|date',
            'heurs' => 'required|string|max:10',
            'produits' => 'required|array|min:1',
            'produits.*.id' => 'nullable|integer|exists:produits,id',
            'produits.*.designation' => 'required|string|max:255',
            'produits.*.dosage' => 'nullable|string|max:50',
            'produits.*.quantite' => 'required|integer|min:1',
            'produits.*.prix_unitaire' => 'required|numeric|min:0',
            'ordonnance' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
            'mode_paiement_id' => 'nullable|exists:modes_paiement,id',
            'montant_livraison_id' => 'nullable|exists:montants_livraison,id',
            'commentaire' => 'nullable|string',
        ]);

        $client = $validated['client_id']
            ? Client::findOrFail($validated['client_id'])
            : Client::create([
                'nom' => $validated['client_nom'],
                'prenom' => ! empty(trim($validated['client_prenom'] ?? '')) ? trim($validated['client_prenom']) : null,
                'tel' => $validated['client_tel'],
                'adresse' => $validated['client_adresse'],
            ]);

        if ($validated['client_id'] && isset($validated['client_nom'])) {
            $client->update([
                'nom' => $validated['client_nom'],
                'prenom' => ! empty(trim($validated['client_prenom'] ?? '')) ? trim($validated['client_prenom']) : null,
                'tel' => $validated['client_tel'],
                'adresse' => $validated['client_adresse'],
            ]);
        }

        $ordonnanceId = $commande->ordonnance_id;
        if ($request->hasFile('ordonnance')) {
            $file = $request->file('ordonnance');
            $ext = $file->getClientOriginalExtension();
            $path = $file->storeAs('ordonnances/'.now()->format('Y-m'), uniqid().'.'.$ext, 'public');
            $ordonnance = Ordonnance::create(['urlfile' => $path]);
            $ordonnanceId = $ordonnance->id;
        }

        $commande->update([
            'client_id' => $client->id,
            'pharmacie_id' => $validated['pharmacie_id'],
            'ordonnance_id' => $ordonnanceId,
            'mode_paiement_id' => $validated['mode_paiement_id'] ?? null,
            'montant_livraison_id' => $validated['montant_livraison_id'] ?? null,
            'date' => $validated['date'],
            'heurs' => $validated['heurs'],
            'commentaire' => $validated['commentaire'] ?? null,
            'beneficiaire' => $validated['beneficiaire'] ?? null,
        ]);

        $commande->produits()->detach();
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

        return redirect()->route('commandes.show', $commande)->with('status', "Commande {$commande->numero} mise à jour.");
    }

    public function bulkAnnuler(Request $request): RedirectResponse
    {
        $this->authorize('bulkAnnuler', Commande::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:commandes,id',
            'motif_annulation' => ['required', 'string', 'max:100', Rule::exists('motifs_annulation', 'slug')],
            'note_annulation' => 'nullable|string|max:1000',
        ]);

        $query = Commande::whereIn('id', $validated['ids']);
        if ($request->user()?->pharmacie_id && ! $request->user()?->hasAnyRole(['admin', 'super_admin'])) {
            $query->where('pharmacie_id', $request->user()->pharmacie_id);
        }

        $count = $query->whereNotIn('status', ['annulee'])->update([
            'status' => 'annulee',
            'status_pharmacie' => 'annulee',
            'motif_annulation' => $validated['motif_annulation'],
            'note_annulation' => $validated['note_annulation'] ?? null,
        ]);

        return back()->with('status', "{$count} commande(s) annulée(s).");
    }

    public function store(StoreCommandeRequest $request): RedirectResponse
    {
        $data = $request->getDataForService();
        $commande = $this->commandeService->create($data, $request->file('ordonnance'));

        return redirect()->route('commandes.index')->with('status', "Commande {$commande->numero} créée.");
    }

    public function updateStatus(Request $request, Commande $commande): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:nouvelle,en_attente,validee,retiree,annulee',
            'motif_annulation' => ['required_if:status,annulee', 'nullable', 'string', 'max:100', Rule::exists('motifs_annulation', 'slug')],
            'note_annulation' => 'nullable|string|max:1000',
        ]);

        if ($validated['status'] === 'retiree' && $commande->status_pharmacie !== 'livre') {
            return back()->with('error', 'La pharmacie doit d\'abord confirmer la remise au livreur avant de marquer la commande comme livrée.');
        }

        if ($validated['status'] === 'validee' && $commande->status === 'en_attente' && ! $commande->acceptation_client) {
            return back()->with('error', 'Le client doit avoir validé le coût total avant de valider la commande.');
        }

        if ($validated['status'] === 'validee' && $commande->parent_id === null) {
            $enfantsNonValides = $commande->enfants()->where('status', 'nouvelle')->exists();
            if ($enfantsNonValides) {
                return back()->with('error', 'La 2ème pharmacie n\'a pas encore validé les produits renvoyés.');
            }
        }

        $statusPharmacie = match ($validated['status']) {
            'validee' => 'valide_a_preparer',
            'annulee' => 'annulee',
            default => $commande->status_pharmacie, // conserver l'état pharmacie actuel
        };

        $commande->update([
            'status' => $validated['status'],
            'status_pharmacie' => $statusPharmacie,
            'motif_annulation' => $validated['status'] === 'annulee' ? ($validated['motif_annulation'] ?? null) : null,
            'note_annulation' => $validated['status'] === 'annulee' ? ($validated['note_annulation'] ?? null) : null,
        ]);

        // Si validation de la commande parente, valider aussi les commandes enfants (déjà en_attente)
        if ($validated['status'] === 'validee' && $commande->parent_id === null) {
            $commande->enfants()->where('status', 'en_attente')->update([
                'status' => 'validee',
                'status_pharmacie' => 'valide_a_preparer',
            ]);
        }

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

    /**
     * Requête de base liste commandes : périmètre utilisateur (pharmacie, pas d’enfants pour admin).
     */
    private function commandesIndexBaseQuery(Request $request): Builder
    {
        return Commande::query()
            ->when($request->user()?->pharmacie_id, fn ($q) => $q->where('pharmacie_id', $request->user()->pharmacie_id))
            ->when($request->user()?->hasAnyRole(['admin', 'super_admin', 'agent_call_center']), fn ($q) => $q->whereNull('parent_id'));
    }

    private function applyCommandeIndexSearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->where('numero', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($q) => $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('tel', 'like', "%{$search}%"))
                ->orWhereHas('produits', fn ($q) => $q->where('designation', 'like', "%{$search}%"));
        });
    }

    /**
     * Date exacte (query) prioritaire sur période. Date effective = date commande ou jour de création si null.
     */
    private function applyCommandeIndexTemporalFilters(Builder $query, Request $request): void
    {
        $date = $request->input('date');
        $periode = $request->input('periode');

        if ($date) {
            $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) = ?', [$date]);

            return;
        }

        if (! $periode) {
            return;
        }

        match ($periode) {
            'aujourdhui' => $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) = ?', [now()->toDateString()]),
            'semaine' => $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) >= ?', [now()->copy()->startOfWeek()->toDateString()]),
            'mois' => $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) >= ?', [now()->copy()->startOfMonth()->toDateString()]),
            default => null,
        };
    }
}
