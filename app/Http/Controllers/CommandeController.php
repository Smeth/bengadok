<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkAnnulerCommandesRequest;
use App\Http\Requests\StoreCommandeRequest;
use App\Http\Requests\UpdateCommandeComplementairesRequest;
use App\Http\Requests\UpdateCommandeRequest;
use App\Http\Requests\UpdateCommandeStatusRequest;
use App\Models\Client;
use App\Models\Commande;
use App\Models\ModePaiement;
use App\Models\Pharmacie;
use App\Models\Zone;
use App\Services\CommandeAdminService;
use App\Services\CommandeDateFormatter;
use App\Services\CommandeDetailPresenter;
use App\Services\CommandeEntityResolverService;
use App\Services\CommandeIndexService;
use App\Services\CommandeMontantCalculator;
use App\Services\CommandeReferentielsService;
use App\Services\CommandeService;
use App\Services\PharmacieProximiteService;
use App\Support\CommandeOrdonnanceUploads;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommandeController extends Controller
{
    public function __construct(
        private PharmacieProximiteService $pharmacieService,
        private CommandeService $commandeService,
        private CommandeIndexService $indexService,
        private CommandeReferentielsService $referentielsService,
        private CommandeDetailPresenter $detailPresenter,
        private CommandeAdminService $adminService,
    ) {}

    public function rechercherPharmacieProche(Request $request)
    {
        $adresse = $request->input('adresse', '');
        $exclurePharmacieId = $request->integer('exclure_pharmacie_id', 0) ?: null;
        $pharmacies = $this->pharmacieService->trouverPharmaciesProches($adresse, $exclurePharmacieId);

        return response()->json(['pharmacies' => $pharmacies]);
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $this->authorize('viewAny', Commande::class);

        return Inertia::render('Commandes/Index', $this->indexService->paginatedIndex($user, $request));
    }

    public function referentiels(Request $request)
    {
        $user = $request->user();
        if (! $user?->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])) {
            abort(403);
        }

        $partenairesUniquement = $request->query('pharmacies', 'partenaires') !== 'toutes';

        return response()->json($this->referentielsService->all($partenairesUniquement));
    }

    public function quickCreatePharmacie(
        Request $request,
        CommandeEntityResolverService $resolver,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user?->hasAnyRole(['admin', 'super_admin']), 403);

        $validated = $request->validate([
            'designation' => 'required|string|max:200',
            'arrondissement' => 'nullable|string|max:100',
            'zone_id' => 'nullable|integer|exists:zones,id',
            'telephone' => 'nullable|string|max:20',
            'adresse' => 'nullable|string|max:500',
        ]);

        $name = trim($validated['designation']);
        $existing = $resolver->findPharmacieByName($name);
        if ($existing) {
            $existing->load(['zone', 'typePharmacie', 'heurs']);

            return response()->json([
                'pharmacie' => $existing,
                'created' => false,
                'message' => 'Une pharmacie correspondante existe déjà et a été sélectionnée.',
            ]);
        }

        $arrondissement = $validated['arrondissement'] ?? null;
        if (($arrondissement === null || $arrondissement === '') && ! empty($validated['zone_id'])) {
            $arrondissement = Zone::query()->find((int) $validated['zone_id'])?->designation;
        }

        $pharmacie = $resolver->createMinimalPharmacie(
            $name,
            $arrondissement,
            $validated['telephone'] ?? null,
            $validated['adresse'] ?? null,
            'Créée depuis la saisie manuelle (Gestion commandes).',
            estPartenaire: false,
        );
        $pharmacie->load(['zone', 'typePharmacie', 'heurs']);

        return response()->json([
            'pharmacie' => $pharmacie,
            'created' => true,
            'message' => 'Pharmacie non partenaire créée. Complétez la fiche dans le module Pharmacies si besoin.',
        ], 201);
    }

    public function show(Request $request, Commande $commande)
    {
        $this->authorize('view', $commande);

        $commande->load($this->detailPresenter->showRelations($request->user()));
        $this->detailPresenter->markDejaRelancee($commande);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['commande' => $this->detailPresenter->toDetailPayload($commande)]);
        }

        return redirect()->route('commandes.index', ['detail' => $commande->id]);
    }

    public function recu(Request $request, Commande $commande, CommandeDateFormatter $dateFormatter)
    {
        $this->authorize('view', $commande);
        if (! in_array($commande->status, Commande::STATUTS_REUSSIS, true)) {
            return redirect()->route('commandes.index', ['detail' => $commande->id])
                ->with('error', 'Le reçu n\'est disponible que pour les commandes livrées.');
        }

        $commande->load(['client', 'pharmacie', 'produits', 'modePaiement', 'montantLivraison']);

        $produitsRecu = $commande->produits->filter(
            fn ($p) => CommandeMontantCalculator::pivotIncluseDansRecu($p->pivot->status ?? null)
        );
        $montants = CommandeMontantCalculator::fromProduitsRelation($produitsRecu);

        $viewData = [
            'commande' => $commande,
            'produitsRecu' => $produitsRecu,
            'dateAffichage' => $dateFormatter->formatDateHeure($commande),
            'sousTotal' => $montants['prix_lignes'],
        ];

        if ($request->boolean('download')) {
            $pdf = Pdf::loadView('recu', [...$viewData, 'hideActions' => true]);

            return $pdf->download('recu-commande-'.$commande->numero.'.pdf');
        }

        return view('recu', $viewData);
    }

    public function edit(Request $request, Commande $commande): Response|RedirectResponse
    {
        $this->authorize('update', $commande);
        if (! in_array($commande->status, ['nouvelle', 'en_attente'])) {
            return redirect()->route('commandes.index', ['detail' => $commande->id])
                ->with('error', 'Seules les commandes « nouvelle » ou « en attente » peuvent être modifiées.');
        }

        $commande->load(['client', 'pharmacie', 'produits', 'ordonnance.verification', 'modePaiement', 'montantLivraison']);

        return Inertia::render('Commandes/Edit', [
            'commande' => $commande,
            'pharmacies' => Pharmacie::with('zone')->partenaires()->get(),
            'modesPaiement' => ModePaiement::all(),
            'arrondissements' => Client::ARRONDISSEMENTS,
        ]);
    }

    public function update(UpdateCommandeRequest $request, Commande $commande): RedirectResponse
    {
        if (! in_array($commande->status, ['nouvelle', 'en_attente'])) {
            return back()->with('error', 'Seules les commandes « nouvelle » ou « en attente » peuvent être modifiées.');
        }

        $commande = $this->commandeService->update(
            $commande,
            $request->validated(),
            ...CommandeOrdonnanceUploads::split($request),
        );

        return redirect()->route('commandes.index', ['detail' => $commande->id])
            ->with('status', "Commande {$commande->numero} mise à jour.");
    }

    public function updateComplementaires(UpdateCommandeComplementairesRequest $request, Commande $commande): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->adminService->updateComplementaires($commande, $request->validated());

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            $commande->load(['piecesJointes.uploadedBy']);

            return response()->json(['commande' => $this->detailPresenter->toDetailPayload($commande)]);
        }

        return back()->with('status', 'Informations complémentaires enregistrées.');
    }

    public function bulkAnnuler(BulkAnnulerCommandesRequest $request): RedirectResponse
    {
        $result = $this->adminService->bulkAnnuler($request->user(), $request->validated());

        return back()->with('status', "{$result['count']} commande(s) annulée(s).");
    }

    public function store(StoreCommandeRequest $request): RedirectResponse
    {
        try {
            $data = $request->getDataForService();
            $overrides = [];
            if ($request->input('_return_hub') === 'gestion' && $request->user()?->hasAnyRole(['admin', 'super_admin'])) {
                $overrides = \App\Support\CommandeInitialStatusOverrides::fromManualEntry(
                    (string) ($data['initial_status'] ?? 'nouvelle'),
                    $data['date'] ?? null,
                    isset($data['heurs']) ? (string) $data['heurs'] : null,
                );
            }
            [$ordonnanceFile, $extraOrdonnanceFiles] = CommandeOrdonnanceUploads::split($request);
            $commande = $this->commandeService->create(
                $data,
                $ordonnanceFile,
                $overrides,
                $extraOrdonnanceFiles,
            );
        } catch (\RuntimeException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        if ($request->input('_return_hub') === 'gestion') {
            return redirect()
                ->route('db-commandes.index', [
                    'tab' => 'commandes',
                    'detail' => $commande->id,
                ])
                ->with('status', "Commande {$commande->numero} créée avec succès.");
        }

        return redirect()->route('commandes.index')->with(
            'status',
            "Commande {$commande->numero} créée avec succès.",
        );
    }

    public function updateStatus(UpdateCommandeStatusRequest $request, Commande $commande): RedirectResponse
    {
        $validated = $request->validated();

        $error = $this->adminService->validateStatusTransition($commande, $validated['status']);
        if ($error !== null) {
            return back()->with('error', $error);
        }

        $this->adminService->updateStatus($commande, $validated);

        return back()->with(
            'status',
            $this->adminService->statusSuccessMessage($commande, $validated['status']),
        );
    }

    public function setLivreur(Request $request, Commande $commande): RedirectResponse
    {
        $this->authorize('assignLivreur', $commande);

        $validated = $request->validate([
            'livreur_id' => 'sometimes|nullable|integer|exists:livreurs,id',
        ]);

        if (! array_key_exists('livreur_id', $validated)) {
            return back();
        }

        $error = $this->adminService->setLivreur($commande, $validated['livreur_id']);
        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back();
    }

    public function setAcceptationClient(Request $request, Commande $commande): RedirectResponse
    {
        $this->authorize('manageStatut', $commande);

        $validated = $request->validate([
            'acceptation_client' => 'required|boolean',
        ]);

        $this->adminService->setAcceptationClient($commande, $validated['acceptation_client']);

        return back();
    }

    public function setMontantLivraison(Request $request, Commande $commande): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('manageStatut', $commande);

        $validated = $request->validate([
            'montant_livraison_id' => 'required|exists:montants_livraison,id',
        ]);

        try {
            $this->adminService->setMontantLivraison($commande, (int) $validated['montant_livraison_id']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($this->adminService->detailPayloadAfterMontantLivraison($commande));
        }

        return back();
    }

    public function setModePaiement(Request $request, Commande $commande): RedirectResponse
    {
        $this->authorize('update', $commande);

        $validated = $request->validate([
            'mode_paiement_id' => 'required|exists:modes_paiement,id',
        ]);

        $error = $this->adminService->setModePaiement($commande, (int) $validated['mode_paiement_id']);
        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back();
    }
}
