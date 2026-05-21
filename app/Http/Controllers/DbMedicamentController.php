<?php

namespace App\Http\Controllers;

use App\Models\DbMedicament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestion de la base locale DB médicament.
 * Module isolé : les données ne sont pas utilisées par les commandes, le catalogue ou la recherche produit.
 */
class DbMedicamentController extends Controller
{
    /** Phrase à saisir pour confirmer le vidage intégral de la base locale. */
    public const PURGE_ALL_CONFIRMATION_PHRASE = 'VIDER BASE MEDICAMENT';

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

    public function destroyBulk(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:db_medicaments,id',
        ]);

        DbMedicament::query()->whereIn('id', $validated['ids'])->delete();

        $n = count($validated['ids']);

        return redirect()->route('medicaments.index', ['onglet' => 'db_medicament'])
            ->with('status', $n > 1
                ? sprintf('%d références supprimées de la base locale.', $n)
                : 'Référence supprimée de la base locale.');
    }

    public function destroy(Request $request, DbMedicament $dbMedicament): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $dbMedicament->delete();

        return redirect()->route('medicaments.index', ['onglet' => 'db_medicament'])
            ->with('status', 'Référence supprimée.');
    }

    public function purgeAll(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'confirmation' => ['required', Rule::in([self::PURGE_ALL_CONFIRMATION_PHRASE])],
        ]);

        $count = DbMedicament::query()->count();
        DbMedicament::query()->delete();

        return redirect()->route('medicaments.index', ['onglet' => 'db_medicament'])
            ->with('status', $count > 0
                ? sprintf('Base locale médicaments vidée (%d entrée(s)).', $count)
                : 'La base locale médicaments était déjà vide.');
    }

    private function authorizeAdmin(Request $request): void
    {
        if (! $request->user()?->hasAnyRole(['admin', 'super_admin'])) {
            abort(403);
        }
    }
}
