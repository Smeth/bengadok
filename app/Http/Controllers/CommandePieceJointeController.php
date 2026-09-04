<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\CommandePieceJointe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommandePieceJointeController extends Controller
{
    public function fichier(Request $request, CommandePieceJointe $pieceJointe): StreamedResponse
    {
        $this->authorize('viewFile', $pieceJointe);

        $path = $pieceJointe->urlfile;
        if (! is_string($path) || $path === '') {
            abort(404);
        }

        $disk = $pieceJointe->resolveStorageDisk();
        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path, headers: [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(Request $request, Commande $commande)
    {
        $user = $request->user();
        abort_unless($user && $user->pharmacie_id, 403);
        abort_unless((int) $commande->pharmacie_id === (int) $user->pharmacie_id, 403);
        abort_unless($commande->status !== 'annulee', 422);

        $validated = $request->validate([
            'fichier' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
            'label' => 'nullable|string|max:120',
        ]);

        CommandePieceJointe::storeFromUpload(
            $validated['fichier'],
            $commande,
            $user,
            $validated['label'] ?? null,
        );

        return back()->with('status', 'Pièce jointe enregistrée.');
    }

    public function destroy(Request $request, Commande $commande, CommandePieceJointe $pieceJointe)
    {
        abort_unless((int) $pieceJointe->commande_id === (int) $commande->id, 404);
        $this->authorize('delete', $pieceJointe);

        $pieceJointe->deleteStoredFile();
        $pieceJointe->delete();

        return back()->with('status', 'Pièce jointe supprimée.');
    }
}
