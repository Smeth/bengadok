<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ClientsDataResetService;
use App\Services\CommandesDataResetService;
use App\Services\FullDataResetExceptAdminsService;
use App\Services\PharmacyDataResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ResetController extends Controller
{
    /**
     * Réinitialise la base comme une installation neuve (données de démonstration).
     * Uniquement en environnement local et pour les super_admin.
     */
    public function __invoke(): RedirectResponse
    {
        if (config('app.env') !== 'local') {
            return back()->with(
                'error',
                'Cette opération n\'est pas disponible dans cet environnement.',
            );
        }

        try {
            Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        } catch (\Throwable $e) {
            return back()->with(
                'error',
                'La réinitialisation a échoué. Veuillez réessayer ou contacter le support.',
            );
        }

        return redirect()->route('login')->with(
            'status',
            'L\'application a été réinitialisée. Veuillez vous reconnecter.',
        );
    }

    /**
     * Supprime toutes les commandes et ordonnances liées.
     */
    public function commandes(
        Request $request,
        CommandesDataResetService $service,
    ): RedirectResponse {
        if (! $request->user()?->hasRole('super_admin')) {
            abort(403);
        }

        if (! PharmacyDataResetService::isAllowed()) {
            return back()->with(
                'error',
                'Cette opération n\'est pas disponible dans cet environnement.',
            );
        }

        $confirm = (string) $request->input('confirmation', '');
        if ($confirm !== 'SUPPRIMER COMMANDES') {
            return back()->with(
                'error',
                'Confirmation invalide : saisissez exactement « SUPPRIMER COMMANDES ».',
            );
        }

        try {
            $stats = $service->resetAllCommandes();
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur : '.$e->getMessage());
        }

        return back()->with(
            'status',
            sprintf(
                'Commandes supprimées : %d commande(s), %d ordonnance(s).',
                $stats['commandes'],
                $stats['ordonnances'],
            ),
        );
    }

    /**
     * Supprime commandes, clients et regroupements de doublons clients.
     */
    public function clients(
        Request $request,
        ClientsDataResetService $service,
    ): RedirectResponse {
        if (! $request->user()?->hasRole('super_admin')) {
            abort(403);
        }

        if (! PharmacyDataResetService::isAllowed()) {
            return back()->with(
                'error',
                'Cette opération n\'est pas disponible dans cet environnement.',
            );
        }

        $confirm = (string) $request->input('confirmation', '');
        if ($confirm !== 'SUPPRIMER CLIENTS') {
            return back()->with(
                'error',
                'Confirmation invalide : saisissez exactement « SUPPRIMER CLIENTS ».',
            );
        }

        try {
            $stats = $service->resetAllClients();
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur : '.$e->getMessage());
        }

        return back()->with(
            'status',
            sprintf(
                'Données clients supprimées : %d client(s), %d groupe(s) de doublons, %d commande(s), %d ordonnance(s).',
                $stats['clients'],
                $stats['groupes_doublons'],
                $stats['commandes'],
                $stats['ordonnances'],
            ),
        );
    }

    /**
     * Supprime toutes les pharmacies, commandes, ordonnances liées et utilisateurs de pharmacie.
     */
    public function pharmacies(Request $request, PharmacyDataResetService $service): RedirectResponse
    {
        if (! $request->user()?->hasRole('super_admin')) {
            abort(403);
        }

        if (! PharmacyDataResetService::isAllowed()) {
            return back()->with(
                'error',
                'Cette opération n\'est pas disponible dans cet environnement.',
            );
        }

        $confirm = (string) $request->input('confirmation', '');
        if ($confirm !== 'SUPPRIMER PHARMACIES') {
            return back()->with(
                'error',
                'Confirmation invalide : saisissez exactement « SUPPRIMER PHARMACIES ».',
            );
        }

        try {
            $stats = $service->resetAllPharmacies();
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur : '.$e->getMessage());
        }

        $msg = sprintf(
            'Pharmacies réinitialisées : %d pharmacie(s), %d commande(s), %d ordonnance(s), %d utilisateur(s) supprimé(s).',
            $stats['pharmacies'],
            $stats['commandes'],
            $stats['ordonnances'],
            $stats['users'],
        );

        return back()->with('status', $msg);
    }

    /**
     * Vide les données métier (commandes, clients, pharmacies, catalogue, utilisateurs hors admin).
     */
    public function full(
        Request $request,
        FullDataResetExceptAdminsService $service,
    ): RedirectResponse {
        if (! $request->user()?->hasRole('super_admin')) {
            abort(403);
        }

        if (! PharmacyDataResetService::isAllowed()) {
            return back()->with(
                'error',
                'Cette opération n\'est pas disponible dans cet environnement.',
            );
        }

        $confirm = (string) $request->input('confirmation', '');
        if ($confirm !== 'VIDER TOUTES LES DONNEES') {
            return back()->with(
                'error',
                'Confirmation invalide : saisissez exactement « VIDER TOUTES LES DONNEES ».',
            );
        }

        try {
            $stats = $service->reset();
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur : '.$e->getMessage());
        }

        return back()->with(
            'status',
            sprintf(
                'Réinitialisation effectuée : %d commande(s), %d ordonnance(s), %d client(s), %d pharmacie(s), %d médicament(s) au catalogue, %d compte(s) utilisateur supprimé(s). Les comptes administrateur sont conservés.',
                $stats['commandes'],
                $stats['ordonnances'],
                $stats['clients'],
                $stats['pharmacies'],
                $stats['produits'],
                $stats['users'],
            ),
        );
    }
}
