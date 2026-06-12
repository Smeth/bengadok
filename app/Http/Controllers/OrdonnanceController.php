<?php

namespace App\Http\Controllers;

use App\Models\Ordonnance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdonnanceController extends Controller
{
    public function fichier(Request $request, Ordonnance $ordonnance): StreamedResponse
    {
        $this->authorize('viewFile', $ordonnance);

        $path = $ordonnance->urlfile;
        if (! is_string($path) || $path === '') {
            abort(404);
        }

        $disk = $ordonnance->resolveStorageDisk();
        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path, headers: [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
