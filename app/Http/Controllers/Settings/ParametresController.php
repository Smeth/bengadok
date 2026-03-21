<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Heur;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\MontantLivraison;
use App\Models\TypePharmacie;
use App\Models\Zone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParametresController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/Parametres', [
            'zones'             => Zone::withCount('pharmacies')->orderBy('designation')->get(),
            'modesPaiement'     => ModePaiement::withCount('commandes')->orderBy('designation')->get(),
            'montantsLivraison' => MontantLivraison::withCount('commandes')->orderBy('designation')->get(),
            'livreurs'          => Livreur::withCount('commandes')->orderBy('nom')->get(),
            'heurs'             => Heur::withCount('pharmacies')->orderBy('ouverture')->get(),
            'typesPharmacie'    => TypePharmacie::with('heurs')->withCount('pharmacies')->orderBy('designation')->get(),
        ]);
    }

    // ── Zones ──────────────────────────────────────────────────────────────

    public function storeZone(Request $request)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:zones,designation',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
        ]);
        Zone::create($validated);
        return back()->with('success', 'Zone créée avec succès.');
    }

    public function updateZone(Request $request, Zone $zone)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:zones,designation,' . $zone->id,
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
        ]);
        $zone->update($validated);
        return back()->with('success', 'Zone mise à jour.');
    }

    public function destroyZone(Zone $zone)
    {
        if ($zone->pharmacies()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des pharmacies sont rattachées à cette zone.');
        }
        $zone->delete();
        return back()->with('success', 'Zone supprimée.');
    }

    // ── Modes de paiement ──────────────────────────────────────────────────

    public function storeModePaiement(Request $request)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:modes_paiement,designation',
        ]);
        ModePaiement::create($validated);
        return back()->with('success', 'Mode de paiement créé.');
    }

    public function updateModePaiement(Request $request, ModePaiement $modePaiement)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:modes_paiement,designation,' . $modePaiement->id,
        ]);
        $modePaiement->update($validated);
        return back()->with('success', 'Mode de paiement mis à jour.');
    }

    public function destroyModePaiement(ModePaiement $modePaiement)
    {
        if ($modePaiement->commandes()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des commandes utilisent ce mode de paiement.');
        }
        $modePaiement->delete();
        return back()->with('success', 'Mode de paiement supprimé.');
    }

    // ── Montants de livraison ──────────────────────────────────────────────

    public function storeMontantLivraison(Request $request)
    {
        $validated = $request->validate([
            'designation' => 'required|numeric|min:0',
        ]);
        MontantLivraison::create($validated);
        return back()->with('success', 'Montant de livraison créé.');
    }

    public function updateMontantLivraison(Request $request, MontantLivraison $montantLivraison)
    {
        $validated = $request->validate([
            'designation' => 'required|numeric|min:0',
        ]);
        $montantLivraison->update($validated);
        return back()->with('success', 'Montant de livraison mis à jour.');
    }

    public function destroyMontantLivraison(MontantLivraison $montantLivraison)
    {
        if ($montantLivraison->commandes()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des commandes utilisent ce montant.');
        }
        $montantLivraison->delete();
        return back()->with('success', 'Montant de livraison supprimé.');
    }

    // ── Livreurs ──────────────────────────────────────────────────────────

    public function storeLivreur(Request $request)
    {
        $validated = $request->validate([
            'nom'    => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'tel'    => 'required|string|max:20',
        ]);
        Livreur::create($validated);
        return back()->with('success', 'Livreur créé.');
    }

    public function updateLivreur(Request $request, Livreur $livreur)
    {
        $validated = $request->validate([
            'nom'    => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'tel'    => 'required|string|max:20',
        ]);
        $livreur->update($validated);
        return back()->with('success', 'Livreur mis à jour.');
    }

    public function destroyLivreur(Livreur $livreur)
    {
        if ($livreur->commandes()->exists()) {
            return back()->with('error', 'Impossible de supprimer : ce livreur est associé à des commandes.');
        }
        $livreur->delete();
        return back()->with('success', 'Livreur supprimé.');
    }

    // ── Horaires ──────────────────────────────────────────────────────────

    public function storeHeur(Request $request)
    {
        $validated = $request->validate([
            'ouverture' => 'required|string|max:5',
            'fermeture' => 'required|string|max:5',
        ]);
        Heur::create($validated);
        return back()->with('success', 'Horaire créé.');
    }

    public function updateHeur(Request $request, Heur $heur)
    {
        $validated = $request->validate([
            'ouverture' => 'required|string|max:5',
            'fermeture' => 'required|string|max:5',
        ]);
        $heur->update($validated);
        return back()->with('success', 'Horaire mis à jour.');
    }

    public function destroyHeur(Heur $heur)
    {
        if ($heur->typePharmacies()->exists() || $heur->pharmacies()->exists()) {
            return back()->with('error', 'Impossible de supprimer : cet horaire est utilisé par des pharmacies.');
        }
        $heur->delete();
        return back()->with('success', 'Horaire supprimé.');
    }

    // ── Types de pharmacie ─────────────────────────────────────────────────

    public function storeTypePharmacie(Request $request)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:type_pharmacies,designation',
            'heurs_id'    => 'nullable|exists:heurs,id',
        ]);
        TypePharmacie::create($validated);
        return back()->with('success', 'Type de pharmacie créé.');
    }

    public function updateTypePharmacie(Request $request, TypePharmacie $typePharmacie)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:type_pharmacies,designation,' . $typePharmacie->id,
            'heurs_id'    => 'nullable|exists:heurs,id',
        ]);
        $typePharmacie->update($validated);
        return back()->with('success', 'Type de pharmacie mis à jour.');
    }

    public function destroyTypePharmacie(TypePharmacie $typePharmacie)
    {
        if ($typePharmacie->pharmacies()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des pharmacies utilisent ce type.');
        }
        $typePharmacie->delete();
        return back()->with('success', 'Type de pharmacie supprimé.');
    }
}
