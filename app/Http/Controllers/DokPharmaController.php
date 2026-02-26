<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokPharmaController extends Controller
{
    public function index(Request $request): Response
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (!$pharmacieId) {
            return Inertia::render('DokPharma/Index', [
                'commandes' => ['data' => [], 'links' => []],
                'stats' => ['nouvelles' => 0, 'a_preparer' => 0, 'livrees' => 0],
                'onglet' => $request->input('onglet', 'nouvelles'),
            ]);
        }

        $onglet = $request->input('onglet', 'nouvelles');
        $statusMap = [
            'nouvelles' => 'nouvelle',
            'a_preparer' => 'a_preparer',
            'validees' => 'validee',
            'livrees' => 'livree',
        ];
        $status = $statusMap[$onglet] ?? 'nouvelle';

        $commandes = Commande::with(['client', 'produits'])
            ->where('pharmacie_id', $pharmacieId)
            ->where('status', $status)
            ->latest('date')
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'nouvelles' => Commande::where('pharmacie_id', $pharmacieId)->where('status', 'nouvelle')->count(),
            'a_preparer' => Commande::where('pharmacie_id', $pharmacieId)->whereIn('status', ['validee', 'a_preparer'])->count(),
            'livrees' => Commande::where('pharmacie_id', $pharmacieId)->where('status', 'livree')->count(),
        ];

        return Inertia::render('DokPharma/Index', [
            'commandes' => $commandes,
            'stats' => $stats,
            'onglet' => $onglet,
        ]);
    }

    public function validerDisponibilite(Request $request, Commande $commande)
    {
        $lignes = $request->input('lignes', []);
        foreach ($lignes as $ligne) {
            $commande->produits()->updateExistingPivot($ligne['produit_id'], [
                'status' => $ligne['status'] ?? 'disponible',
            ]);
        }
        $commande->update(['status' => 'validee']);
        return back();
    }
}
