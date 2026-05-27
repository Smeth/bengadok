<?php

namespace App\Http\Controllers;

use App\Models\Pharmacie;
use App\Services\PharmacieCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PharmacieCreditController extends Controller
{
    public function __construct(
        private PharmacieCreditService $creditService,
    ) {}

    public function recharge(Request $request, Pharmacie $pharmacie): RedirectResponse
    {
        $validated = $request->validate([
            'nombre_credits' => 'required|integer|min:1|max:99999',
            'mode_paiement' => 'required|string|max:80',
            'note' => 'nullable|string|max:2000',
        ]);

        try {
            $this->creditService->recharger(
                $pharmacie,
                (int) $validated['nombre_credits'],
                $validated['mode_paiement'],
                $validated['note'] ?? null,
                $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pharmacies.index', [
                'onglet' => 'credits',
                'pharmacie_id' => $pharmacie->id,
            ])
            ->with('status', 'Crédits ajoutés avec succès.');
    }

    public function updateNote(Request $request, Pharmacie $pharmacie): RedirectResponse
    {
        $validated = $request->validate([
            'note_interne' => 'nullable|string|max:5000',
        ]);

        $this->creditService->updateNoteInterne($pharmacie, $validated['note_interne'] ?? null);

        return back()->with('status', 'Notes enregistrées.');
    }

    public function updateAlerteSeuil(Request $request, Pharmacie $pharmacie): RedirectResponse
    {
        $validated = $request->validate([
            'credits_alerte_seuil' => 'nullable|integer|min:1|max:9999',
        ]);

        $seuil = isset($validated['credits_alerte_seuil'])
            ? (int) $validated['credits_alerte_seuil']
            : null;

        $this->creditService->updateAlerteSeuil($pharmacie, $seuil);

        return back()->with('status', 'Seuil d\'alerte mis à jour.');
    }
}
