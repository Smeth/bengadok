<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Pharmacie;
use App\Services\AdminParapharmaDashboardService;
use App\Services\DokPharmaCommandeActionService;
use App\Services\DokPharmaCommandeIndexService;
use App\Services\PharmacieCreditService;
use App\Services\PharmacieDashboardContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokPharmaController extends Controller
{
    public function __construct(
        private PharmacieDashboardContextResolver $dashboardContext,
        private DokPharmaCommandeIndexService $commandeIndexService,
        private DokPharmaCommandeActionService $commandeActionService,
    ) {}

    public function dashboard(
        Request $request,
        AdminParapharmaDashboardService $parapharmaService,
    ): Response|RedirectResponse {
        $user = $request->user();
        if ($user && $user->hasRole('vendeur') && ! $user->hasRole('gerant')) {
            return redirect('/dok-pharma/commandes');
        }

        $context = $this->dashboardContext->resolve($request);
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
                    'ca_medicaments' => 0,
                    'ca_parapharma' => 0,
                    'ca_total' => 0,
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
        abort_unless($request->user()?->hasRole('gerant'), 403);

        $context = $this->dashboardContext->resolve($request);
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
        abort_unless($request->user()?->hasRole('gerant'), 403);

        $context = $this->dashboardContext->resolve($request);
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

    public function index(Request $request): Response|RedirectResponse
    {
        if ($this->commandeIndexService->userIsVendeurSeul($request)
            && $request->input('onglet', 'nouvelles') === 'livrees') {
            $search = trim((string) $request->input('search', ''));

            return redirect()->route('dok-pharma.commandes', array_filter([
                'onglet' => 'nouvelles',
                'search' => $search !== '' ? $search : null,
            ]));
        }

        return Inertia::render('DokPharma/Index', $this->commandeIndexService->paginatedIndex($request));
    }

    public function validerDisponibilite(Request $request, Commande $commande)
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (! $pharmacieId || $commande->pharmacie_id != $pharmacieId) {
            abort(403);
        }

        $lignes = $request->input('lignes', []);
        if (! is_array($lignes)) {
            $lignes = [];
        }

        $error = $this->commandeActionService->validerDisponibilite(
            $commande,
            $pharmacieId,
            $lignes,
            (string) $request->input('commentaire', ''),
        );

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('status', 'Disponibilité envoyée.');
    }

    public function validerRetrait(Request $request, Commande $commande)
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (! $pharmacieId || $commande->pharmacie_id != $pharmacieId) {
            abort(403);
        }

        $error = $this->commandeActionService->validerRetrait($commande);
        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('status', 'Retrait validé.');
    }
}
