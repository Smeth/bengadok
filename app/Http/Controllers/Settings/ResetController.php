<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

class ResetController extends Controller
{
    /**
     * Réinitialise l'application (migrate:fresh --seed).
     * Uniquement en environnement local et pour les super_admin.
     */
    public function __invoke(): RedirectResponse
    {
        if (config('app.env') !== 'local') {
            return back()->with('error', 'La réinitialisation n\'est autorisée qu\'en environnement local.');
        }

        try {
            Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur lors de la réinitialisation : '.$e->getMessage());
        }

        return redirect()->route('login')->with('status', 'Application réinitialisée avec succès. Veuillez vous reconnecter.');
    }
}
