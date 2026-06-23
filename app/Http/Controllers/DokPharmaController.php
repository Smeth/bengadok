<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Pharmacie;
use App\Models\Produit;
use App\Services\AdminParapharmaDashboardService;
use App\Services\CommandeDateFormatter;
use App\Services\CommandeMontantCalculator;
use App\Services\PharmacieCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DokPharmaController extends Controller
{
    public function __construct(
        private CommandeDateFormatter $commandeDateFormatter,
    ) {}

    /**
     * Dashboard pharmacie : parapharmacie, commissions, crédits (maquette Figma).
     */
    public function dashboard(
        Request $request,
        AdminParapharmaDashboardService $parapharmaService,
    ): Response {
        $user = $request->user();
        if ($user && $user->hasRole('vendeur') && ! $user->hasRole('gerant')) {
            return redirect('/dok-pharma/commandes');
        }

        $context = $this->resolvePharmacieDashboardContext($request);
        $mois = $request->get('mois');

        if ($context['pharmacie_id'] === null) {
            return Inertia::render('DokPharma/Dashboard', [
                'mode' => 'parapharma_pharmacie',
                'pharmacie_id' => null,
                'pharmacie' => null,
                'pharmacies_disponibles' => [],
                'mois' => now()->format('Y-m'),
                'mois_label' => '',
                'mois_options' => [],
                'config' => $parapharmaService->config(),
                'kpis' => [
                    'nb_commandes' => 0,
                    'ca_parapharma' => 0,
                    'credits_disponibles' => 0,
                    'credits_utilises' => 0,
                    'credits_prepayes_total' => 0,
                    'credits_consommes_total' => 0,
                    'cout_credits_consommes' => 0,
                    'commandes_eligibles_credit' => 0,
                    'montant_commission' => 0,
                ],
                'commission_courante' => [
                    'periode_label' => '—',
                    'echeance_label' => '—',
                    'montant' => 0,
                    'statut' => 'en_cours',
                    'statut_label' => 'En cours',
                    'paye_le' => null,
                ],
                'ventes' => [],
                'historique_commissions' => [],
                'commandes_recentes' => [],
            ]);
        }

        $payload = $parapharmaService->build(
            is_string($mois) ? $mois : null,
            $context['pharmacie_id'],
        );

        return Inertia::render('DokPharma/Dashboard', array_merge($payload, [
            'pharmacies_disponibles' => $context['pharmacies_disponibles'],
        ]));
    }

    public function marquerCommissionPayee(
        Request $request,
        AdminParapharmaDashboardService $parapharmaService,
    ): RedirectResponse {
        $context = $this->resolvePharmacieDashboardContext($request);
        abort_unless($context['pharmacie_id'] !== null, 403);

        $validated = $request->validate([
            'mois' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        [$annee, $mois] = array_map('intval', explode('-', $validated['mois']));
        $parapharmaService->marquerCommissionPayee($annee, $mois, $context['pharmacie_id']);

        return redirect()
            ->route('dok-pharma.dashboard', [
                'mois' => $validated['mois'],
                'pharmacie_id' => $context['pharmacie_id'],
            ])
            ->with('success', 'Commission marquée comme payée.');
    }

    public function rechargerCredits(
        Request $request,
        PharmacieCreditService $creditService,
    ): RedirectResponse {
        $context = $this->resolvePharmacieDashboardContext($request);
        abort_unless($context['pharmacie_id'] !== null, 403);

        $pharmacie = Pharmacie::query()->findOrFail($context['pharmacie_id']);

        $validated = $request->validate([
            'nombre_credits' => 'required|integer|min:1|max:99999',
            'mode_paiement' => 'required|string|max:80',
            'note' => 'nullable|string|max:2000',
        ]);

        try {
            $creditService->recharger(
                $pharmacie,
                (int) $validated['nombre_credits'],
                $validated['mode_paiement'],
                $validated['note'] ?? null,
                $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dok-pharma.dashboard', ['pharmacie_id' => $context['pharmacie_id']])
            ->with('success', 'Demande de recharge enregistrée.');
    }

    /**
     * @return array{
     *     pharmacie_id: int|null,
     *     pharmacies_disponibles: array<int, array{id: int, designation: string}>
     * }
     */
    private function resolvePharmacieDashboardContext(Request $request): array
    {
        $user = $request->user();
        $userPharmacieId = $user?->pharmacie_id;

        if (! $userPharmacieId) {
            return ['pharmacie_id' => null, 'pharmacies_disponibles' => []];
        }

        $userPharmacie = Pharmacie::query()->find($userPharmacieId);

        $disponibles = Pharmacie::query()
            ->where(function ($q) use ($userPharmacie, $userPharmacieId) {
                $q->where('id', $userPharmacieId);
                if ($userPharmacie?->proprio_email) {
                    $q->orWhere('proprio_email', $userPharmacie->proprio_email);
                }
            })
            ->orderBy('designation')
            ->get(['id', 'designation']);

        $requestedId = $request->integer('pharmacie_id');
        $activeId = $disponibles->contains('id', $requestedId)
            ? $requestedId
            : $userPharmacieId;

        return [
            'pharmacie_id' => $activeId,
            'pharmacies_disponibles' => $disponibles
                ->map(fn (Pharmacie $p) => ['id' => $p->id, 'designation' => $p->designation])
                ->values()
                ->all(),
        ];
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
                    'date' => $this->commandeDateFormatter->formatDateHeure($c),
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
                            'status' => $p->pivot->status ?? 'en_attente',
                            'quantite_confirmee' => $p->pivot->quantite_confirmee ?? null,
                            'vente_libre' => (bool) ($p->pivot->vente_libre ?? false),
                        ],
                    ])->values(),
                    'ordonnance_id' => $c->ordonnance_id,
                    'ordonnance_url' => $c->ordonnance?->file_url,
                    'ordonnance_is_pdf' => (bool) ($c->ordonnance?->is_pdf ?? false),
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

            $venteLibre = filter_var(
                $ligne['vente_libre'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;

            $pivotData = [
                'status' => $status,
                'quantite_confirmee' => $qteStockee,
                'vente_libre' => $venteLibre,
            ];
            if ($prixUnitaire !== null) {
                $pivotData['prix_unitaire'] = $prixUnitaire;
            }

            $commande->produits()->updateExistingPivot($produitId, $pivotData);

            if (
                in_array($status, ['disponible', 'partiel'], true)
                && ! CommandeMontantCalculator::isParapharmaType($produit->type)
            ) {
                $produit->update([
                    'type' => $venteLibre ? 'Vente libre' : 'Sur ordonnance',
                ]);
            }

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
