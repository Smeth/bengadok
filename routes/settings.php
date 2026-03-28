<?php

use App\Http\Controllers\ClientFrequenceController;
use App\Http\Controllers\Settings\ParametresController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ResetController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::prefix('settings/roles')->name('roles.')->middleware('role:super_admin')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::patch('{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

    Route::inertia('settings/reset', 'settings/Reset')
        ->middleware('role:super_admin')
        ->name('settings.reset');

    Route::post('settings/reset', ResetController::class)
        ->middleware('role:super_admin')
        ->name('settings.reset.store');

    // ── Paramètres métier ──────────────────────────────────────────────────
    Route::prefix('settings/parametres')
        ->name('parametres.')
        ->middleware('role:admin|super_admin')
        ->group(function () {
            Route::get('/', [ParametresController::class, 'index'])->name('index');

            // Zones
            Route::post('zones', [ParametresController::class, 'storeZone'])->name('zones.store');
            Route::patch('zones/{zone}', [ParametresController::class, 'updateZone'])->name('zones.update');
            Route::delete('zones/{zone}', [ParametresController::class, 'destroyZone'])->name('zones.destroy');

            // Modes de paiement
            Route::post('modes-paiement', [ParametresController::class, 'storeModePaiement'])->name('modes-paiement.store');
            Route::patch('modes-paiement/{modePaiement}', [ParametresController::class, 'updateModePaiement'])->name('modes-paiement.update');
            Route::delete('modes-paiement/{modePaiement}', [ParametresController::class, 'destroyModePaiement'])->name('modes-paiement.destroy');

            // Montants de livraison
            Route::post('montants-livraison', [ParametresController::class, 'storeMontantLivraison'])->name('montants-livraison.store');
            Route::patch('montants-livraison/{montantLivraison}', [ParametresController::class, 'updateMontantLivraison'])->name('montants-livraison.update');
            Route::delete('montants-livraison/{montantLivraison}', [ParametresController::class, 'destroyMontantLivraison'])->name('montants-livraison.destroy');

            // Livreurs
            Route::post('livreurs', [ParametresController::class, 'storeLivreur'])->name('livreurs.store');
            Route::patch('livreurs/{livreur}', [ParametresController::class, 'updateLivreur'])->name('livreurs.update');
            Route::delete('livreurs/{livreur}', [ParametresController::class, 'destroyLivreur'])->name('livreurs.destroy');

            // Horaires
            Route::post('heurs', [ParametresController::class, 'storeHeur'])->name('heurs.store');
            Route::patch('heurs/{heur}', [ParametresController::class, 'updateHeur'])->name('heurs.update');
            Route::delete('heurs/{heur}', [ParametresController::class, 'destroyHeur'])->name('heurs.destroy');

            // Types de pharmacie
            Route::post('types-pharmacie', [ParametresController::class, 'storeTypePharmacie'])->name('types-pharmacie.store');
            Route::patch('types-pharmacie/{typePharmacie}', [ParametresController::class, 'updateTypePharmacie'])->name('types-pharmacie.update');
            Route::delete('types-pharmacie/{typePharmacie}', [ParametresController::class, 'destroyTypePharmacie'])->name('types-pharmacie.destroy');

            // Motifs d'annulation (libellés, code, relance, ordre)
            Route::post('motifs-annulation', [ParametresController::class, 'storeMotifAnnulation'])->name('motifs-annulation.store');
            Route::patch('motifs-annulation/{motifAnnulation}', [ParametresController::class, 'updateMotifAnnulation'])->name('motifs-annulation.update');
            Route::delete('motifs-annulation/{motifAnnulation}', [ParametresController::class, 'destroyMotifAnnulation'])->name('motifs-annulation.destroy');

            // Segments de fréquence client (filtre liste clients, libellé sur fiches)
            Route::post('client-frequences', [ClientFrequenceController::class, 'store'])->name('client-frequences.store');
            Route::patch('client-frequences/{clientFrequence}', [ClientFrequenceController::class, 'update'])->name('client-frequences.update');
            Route::delete('client-frequences/{clientFrequence}', [ClientFrequenceController::class, 'destroy'])->name('client-frequences.destroy');

            Route::patch('relance-delai', [ParametresController::class, 'updateRelanceDelai'])->name('relance-delai.update');
        });
});
