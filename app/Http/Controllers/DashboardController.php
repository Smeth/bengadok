<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStatsService $statsService): Response|RedirectResponse
    {
        $user = $request->user();
        if (
            $user
            && $user->hasRole('agent_call_center')
            && ! $user->hasAnyRole(['admin', 'super_admin'])
        ) {
            return redirect('/commandes');
        }

        $period = $request->get('period', 'month');
        $stats = $statsService->build($user->pharmacie_id, is_string($period) ? $period : 'month');

        return Inertia::render('Dashboard', $stats);
    }
}
