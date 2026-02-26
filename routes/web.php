<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DokPharmaController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('commandes')->name('commandes.')->group(function () {
        Route::get('/', [CommandeController::class, 'index'])->name('index');
        Route::get('{commande}', [CommandeController::class, 'show'])->name('show');
        Route::patch('{commande}/status', [CommandeController::class, 'updateStatus'])->name('update-status');
    });

    Route::prefix('dok-pharma')->name('dok-pharma.')->middleware('role:vendeur')->group(function () {
        Route::get('/', [DokPharmaController::class, 'index'])->name('index');
        Route::post('{commande}/valider', [DokPharmaController::class, 'validerDisponibilite'])->name('valider');
    });

    Route::prefix('agent')->name('agent.')->middleware('role:agent_call_center')->group(function () {
        Route::get('/', [AgentController::class, 'index'])->name('index');
        Route::get('nouvelle-commande', [AgentController::class, 'nouvelleCommande'])->name('nouvelle-commande');
        Route::post('commande', [AgentController::class, 'storeCommande'])->name('store-commande');
        Route::get('recherche-pharmacie', [AgentController::class, 'rechercherPharmacie'])->name('recherche-pharmacie');
        Route::get('recherche-client', [AgentController::class, 'rechercherClient'])->name('recherche-client');
        Route::get('recherche-produit', [AgentController::class, 'rechercherProduit'])->name('recherche-produit');
    });
});

require __DIR__.'/settings.php';
