<?php

namespace App\Http\Controllers;

use App\Services\AdminParapharmaDashboardService;
use App\Services\DashboardStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardStatsService $statsService,
        AdminParapharmaDashboardService $parapharmaService,
    ): Response|RedirectResponse {
        $user = $request->user();
        if (
            $user
            && $user->hasRole('agent_call_center')
            && ! $user->hasAnyRole(['admin', 'super_admin'])
        ) {
            return redirect('/commandes');
        }

        if ($this->utiliseDashboardParapharmaAdmin($user)) {
            $activeTab = $request->string('tab')->toString();
            if (! in_array($activeTab, ['parapharma', 'operations'], true)) {
                $activeTab = 'parapharma';
            }

            $mois = $request->get('mois');
            $period = $request->get('period', 'month');
            $period = is_string($period) ? $period : 'month';

            $parapharma = $parapharmaService->build(is_string($mois) ? $mois : null);
            $parapharmaKpis = $parapharma['kpis'];
            unset($parapharma['kpis']);

            $operations = $statsService->build(null, $period);

            return Inertia::render('Dashboard', array_merge($parapharma, $operations, [
                'active_tab' => $activeTab,
                'parapharma_kpis' => $parapharmaKpis,
            ]));
        }

        $period = $request->get('period', 'month');
        $stats = $statsService->build($user->pharmacie_id, is_string($period) ? $period : 'month');

        return Inertia::render('Dashboard', $stats);
    }

    public function marquerCommissionPayee(
        Request $request,
        AdminParapharmaDashboardService $parapharmaService,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($this->utiliseDashboardParapharmaAdmin($user), 403);

        $validated = $request->validate([
            'mois' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        [$annee, $mois] = array_map('intval', explode('-', $validated['mois']));
        $parapharmaService->marquerCommissionPayee($annee, $mois);

        return redirect()
            ->route('dashboard', ['mois' => $validated['mois'], 'tab' => 'parapharma'])
            ->with('success', 'Commission marquée comme payée.');
    }

    private function utiliseDashboardParapharmaAdmin(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return ! $user->pharmacie_id
            && ! $user->hasAnyRole(['gerant', 'vendeur', 'agent_call_center']);
    }
}
