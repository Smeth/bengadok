<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DokPharmaController;
use App\Http\Controllers\NotificationStreamController;
use App\Http\Controllers\PostLoginLoadingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDoublonController;
use App\Http\Controllers\MedicamentController;
use App\Http\Controllers\PharmacieController;
use App\Http\Controllers\PharmacieVendeurController;
use App\Http\Controllers\UtilisateurBackofficeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

// Redirection pour les anciens liens pharmacie (ex. PharmacyLayout)
Route::redirect('reglages', '/settings/profile')->name('reglages');

// Écran de chargement post-connexion (Figma) — auth seulement pour laisser passer avant verified
Route::middleware(['auth'])->get('chargement', PostLoginLoadingController::class)->name('post-login.loading');

// Flux SSE de notifications temps réel
Route::middleware(['auth'])->get('notifications/stream', NotificationStreamController::class)->name('notifications.stream');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('pharmacies')->name('pharmacies.')->group(function () {
        Route::get('/', [PharmacieController::class, 'index'])->name('index');
        Route::post('/', [PharmacieController::class, 'store'])->name('store');
        Route::get('{pharmacie}', [PharmacieController::class, 'show'])->name('show');
        Route::patch('{pharmacie}', [PharmacieController::class, 'update'])->name('update');
        Route::patch('{pharmacie}/toggle-garde', [PharmacieController::class, 'toggleGarde'])->name('toggle-garde');
        Route::delete('{pharmacie}', [PharmacieController::class, 'destroy'])->name('destroy');
        Route::post('{pharmacie}/users', [PharmacieController::class, 'storeUser'])->name('users.store')->middleware('role:admin|super_admin');
        Route::patch('{pharmacie}/users/{user}/reset-password', [PharmacieController::class, 'resetUserPassword'])->name('users.reset-password')->middleware('role:admin|super_admin');
        Route::delete('{pharmacie}/users/{user}', [PharmacieController::class, 'destroyUser'])->name('users.destroy')->middleware('role:admin|super_admin');
    });
    Route::prefix('medicaments')->name('medicaments.')->group(function () {
        Route::get('/', [MedicamentController::class, 'index'])->name('index');
        Route::get('{produit}', [MedicamentController::class, 'show'])->name('show');
    });
    Route::prefix('clients')->name('clients.')->group(function () {
        Route::get('/', [ClientController::class, 'index'])->name('index');
        Route::get('doublons', [ClientDoublonController::class, 'index'])->name('doublons');
        Route::patch('doublons/{groupe}/ignorer', [ClientDoublonController::class, 'ignorer'])->name('doublons.ignorer');
        Route::patch('doublons/{groupe}/verifier', [ClientDoublonController::class, 'verifier'])->name('doublons.verifier');
        Route::patch('doublons/{groupe}/fusionner', [ClientDoublonController::class, 'fusionner'])->name('doublons.fusionner');
        Route::get('{client}', [ClientController::class, 'show'])->name('show');
    });
    Route::prefix('utilisateurs')->name('utilisateurs.')->middleware('role:admin|super_admin')->group(function () {
        Route::get('/', [UtilisateurBackofficeController::class, 'index'])->name('index');
        Route::post('/', [UtilisateurBackofficeController::class, 'store'])->name('store');
        Route::patch('{user}', [UtilisateurBackofficeController::class, 'update'])->name('update');
        Route::patch('{user}/permissions', [UtilisateurBackofficeController::class, 'updatePermissions'])->name('update-permissions');
        Route::delete('{user}', [UtilisateurBackofficeController::class, 'destroy'])->name('destroy');
    });

    //Processus vendeur -> pharmacie
    Route::prefix('pharmacie')->name('pharmacie.')->middleware('role:gerant')->group(function () {
        Route::get('vendeurs', [PharmacieVendeurController::class, 'index'])->name('vendeurs.index');
        Route::post('vendeurs', [PharmacieVendeurController::class, 'store'])->name('vendeurs.store');
    });

    //Commande
    Route::prefix('commandes')->name('commandes.')->group(function () {
        Route::get('/', [CommandeController::class, 'index'])->name('index');
        Route::get('recherche-pharmacie-proche', [CommandeController::class, 'rechercherPharmacieProche'])->name('recherche-pharmacie');
        Route::post('bulk-annuler', [CommandeController::class, 'bulkAnnuler'])->name('bulk-annuler')->middleware('role:admin|super_admin|agent_call_center');
        Route::post('/', [CommandeController::class, 'store'])->name('store')->middleware('role:admin|super_admin|agent_call_center');
        Route::get('{commande}/recu', [CommandeController::class, 'recu'])->name('recu');
        Route::get('{commande}/edit', [CommandeController::class, 'edit'])->name('edit')->middleware('role:admin|super_admin|agent_call_center');
        Route::patch('{commande}', [CommandeController::class, 'update'])->name('update')->middleware('role:admin|super_admin|agent_call_center');
        Route::get('{commande}', [CommandeController::class, 'show'])->name('show');
        Route::patch('{commande}/status', [CommandeController::class, 'updateStatus'])->name('update-status');
        Route::patch('{commande}/acceptation-client', [CommandeController::class, 'setAcceptationClient'])->name('acceptation-client');
        Route::patch('{commande}/montant-livraison', [CommandeController::class, 'setMontantLivraison'])->name('montant-livraison');
    });

    Route::prefix('dok-pharma')->name('dok-pharma.')->middleware('role:vendeur|gerant')->group(function () {
        Route::get('/', [DokPharmaController::class, 'dashboard'])->name('dashboard');
        Route::get('/commandes', [DokPharmaController::class, 'index'])->name('commandes');
        Route::post('{commande}/valider', [DokPharmaController::class, 'validerDisponibilite'])->name('valider');
        Route::post('{commande}/valider-retrait', [DokPharmaController::class, 'validerRetrait'])->name('valider-retrait');
    });

    Route::prefix('agent')->name('agent.')->middleware('role:agent_call_center|admin|super_admin')->group(function () {
        Route::get('/', [AgentController::class, 'index'])->name('index');
        Route::get('nouvelle-commande', [AgentController::class, 'nouvelleCommande'])->name('nouvelle-commande');
        Route::post('commande', [AgentController::class, 'storeCommande'])->name('store-commande');
        Route::post('commande/{commande}/renvoyer-pharmacie', [AgentController::class, 'renvoyerPharmacie'])->name('renvoyer-pharmacie');
        Route::post('commande/{commande}/renvoyer-pharmacie-partiel', [AgentController::class, 'renvoyerPharmaciePartiel'])->name('renvoyer-pharmacie-partiel');
        Route::get('recherche-pharmacie', [AgentController::class, 'rechercherPharmacie'])->name('recherche-pharmacie');
        Route::get('recherche-client', [AgentController::class, 'rechercherClient'])->name('recherche-client');
        Route::get('recherche-produit', [AgentController::class, 'rechercherProduit'])->name('recherche-produit');
    });
});

require __DIR__.'/settings.php';
