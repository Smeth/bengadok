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
                'stats' => ['nouvelles' => 0, 'a_preparer' => 0, 'retirees' => 0, 'livrees' => 0],
                'onglet' => $request->input('onglet', 'nouvelles'),
            ]);
        }

        $onglet = $request->input('onglet', 'nouvelles');
        // Pharmacie ne voit PAS en_attente (visible backoffice uniquement)
        $query = Commande::with(['client', 'produits'])
            ->where('pharmacie_id', $pharmacieId);

        $query->when($onglet === 'nouvelles', fn ($q) => $q->where('status', 'nouvelle'))
            ->when($onglet === 'a_preparer', fn ($q) => $q->whereIn('status', ['validee', 'a_preparer']))
            ->when($onglet === 'retirees', fn ($q) => $q->where('status', 'retiree'))
            ->when($onglet === 'livrees', fn ($q) => $q->where('status', 'livree'))
            ->when(!in_array($onglet, ['nouvelles', 'a_preparer', 'retirees', 'livrees']), fn ($q) => $q->where('status', 'nouvelle'));

        $commandes = $query->latest('date')->latest('created_at')->paginate(10)->withQueryString();

        $stats = [
            'nouvelles' => Commande::where('pharmacie_id', $pharmacieId)->where('status', 'nouvelle')->count(),
            'a_preparer' => Commande::where('pharmacie_id', $pharmacieId)->whereIn('status', ['validee', 'a_preparer'])->count(),
            'retirees' => Commande::where('pharmacie_id', $pharmacieId)->where('status', 'retiree')->count(),
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
        $pharmacieId = $request->user()?->pharmacie_id;
        if (!$pharmacieId || $commande->pharmacie_id != $pharmacieId) {
            abort(403);
        }

        $lignes = $request->input('lignes', []);
        $nbDispo = 0;
        $nbIndispo = 0;

        foreach ($lignes as $ligne) {
            $status = $ligne['status'] ?? 'disponible';
            $qteConfirmee = isset($ligne['quantite_confirmee']) ? (int) $ligne['quantite_confirmee'] : null;
            $commande->produits()->updateExistingPivot($ligne['produit_id'], [
                'status' => $status,
                'quantite_confirmee' => $status === 'partiel' ? $qteConfirmee : null,
            ]);

            if ($status === 'disponible' || ($status === 'partiel' && $qteConfirmee > 0)) {
                $nbDispo++;
            } else {
                $nbIndispo++;
            }
        }

        $commande->load('produits');
        $prixTotal = $commande->produits->sum(function ($p) {
            if ($p->pivot->status === 'disponible') {
                return $p->pivot->quantite * $p->pivot->prix_unitaire;
            }
            if ($p->pivot->status === 'partiel' && $p->pivot->quantite_confirmee > 0) {
                return $p->pivot->quantite_confirmee * $p->pivot->prix_unitaire;
            }
            return 0;
        });

        // Pharmacie traite → en_attente (visible backoffice uniquement, pharmacie ne voit plus)
        $nouveauStatus = 'en_attente';
        if ($nbDispo === 0) {
            $commande->update([
                'status' => $nouveauStatus,
                'pharmacie_refusee_id' => $pharmacieId,
            ]);
        } else {
            $commande->update([
                'status' => $nouveauStatus,
                'prix_total' => $prixTotal,
            ]);
        }

        return back();
    }

    /**
     * Valider le retrait de la commande par le livreur (vendeur confirme remise au livreur).
     */
    public function validerRetrait(Request $request, Commande $commande)
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (!$pharmacieId || $commande->pharmacie_id != $pharmacieId) {
            abort(403);
        }
        if (!in_array($commande->status, ['validee', 'a_preparer'])) {
            return back()->with('error', 'Seules les commandes validées peuvent être retirées.');
        }

        $commande->update(['status' => 'retiree']);
        return back()->with('status', 'Retrait validé.');
    }
}
