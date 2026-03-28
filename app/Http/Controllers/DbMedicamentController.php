<?php

namespace App\Http\Controllers;

use App\Models\DbMedicament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gestion de la base locale DB médicament.
 * Module isolé : les données ne sont pas utilisées par les commandes, le catalogue ou la recherche produit.
 */
class DbMedicamentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'designation' => 'required|string|max:255',
            'dosage' => 'nullable|string|max:120',
            'forme' => 'nullable|string|max:120',
            'prix' => 'required|numeric|min:0',
            'laboratoire' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:80',
            'code_article' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
        ]);

        DbMedicament::query()->create($validated);

        return redirect()->route('medicaments.index', ['onglet' => 'db_medicament'])
            ->with('status', 'Référence ajoutée à la base locale.');
    }

    public function update(Request $request, DbMedicament $dbMedicament): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'designation' => 'required|string|max:255',
            'dosage' => 'nullable|string|max:120',
            'forme' => 'nullable|string|max:120',
            'prix' => 'required|numeric|min:0',
            'laboratoire' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:80',
            'code_article' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
        ]);

        $dbMedicament->update($validated);

        return redirect()->route('medicaments.index', ['onglet' => 'db_medicament'])
            ->with('status', 'Référence mise à jour.');
    }

    public function destroy(Request $request, DbMedicament $dbMedicament): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $dbMedicament->delete();

        return redirect()->route('medicaments.index', ['onglet' => 'db_medicament'])
            ->with('status', 'Référence supprimée.');
    }

    private function authorizeAdmin(Request $request): void
    {
        if (! $request->user()?->hasAnyRole(['admin', 'super_admin'])) {
            abort(403);
        }
    }
}
