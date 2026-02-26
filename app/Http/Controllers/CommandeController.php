<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommandeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Commande::with(['client', 'pharmacie', 'produits', 'livreur'])
            ->when($request->user()?->pharmacie_id, fn($q) => $q->where('pharmacie_id', $request->user()->pharmacie_id));

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('client', fn($q) => $q->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%")
                        ->orWhere('tel', 'like', "%{$search}%"))
                    ->orWhereHas('produits', fn($q) => $q->where('designation', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($periode = $request->input('periode')) {
            match ($periode) {
                'aujourdhui' => $query->whereDate('date', today()),
                'semaine' => $query->where('date', '>=', now()->startOfWeek()),
                'mois' => $query->where('date', '>=', now()->startOfMonth()),
                default => null,
            };
        }

        $commandes = $query->latest('date')->latest('created_at')->paginate(15)->withQueryString();

        $base = Commande::query()->when($request->user()?->pharmacie_id, fn($q) => $q->where('pharmacie_id', $request->user()->pharmacie_id));
        $stats = [
            'nouvelles' => (clone $base)->where('status', 'nouvelle')->count(),
            'en_attente' => (clone $base)->where('status', 'en_attente')->count(),
            'validees' => (clone $base)->where('status', 'validee')->count(),
            'a_preparer' => (clone $base)->where('status', 'a_preparer')->count(),
            'livrees' => (clone $base)->where('status', 'livree')->count(),
            'annulees' => (clone $base)->where('status', 'annulee')->count(),
        ];

        return Inertia::render('Commandes/Index', [
            'commandes' => $commandes,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'periode']),
        ]);
    }

    public function show(Commande $commande): Response
    {
        $commande->load(['client', 'pharmacie', 'produits', 'ordonnance', 'modePaiement', 'livreur', 'montantLivraison']);

        return Inertia::render('Commandes/Show', [
            'commande' => $commande,
        ]);
    }

    public function updateStatus(Request $request, Commande $commande)
    {
        $request->validate(['status' => 'required|in:nouvelle,en_attente,validee,a_preparer,livree,annulee']);

        $commande->update(['status' => $request->status]);

        return back();
    }
}
