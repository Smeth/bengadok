<?php

namespace App\Http\Controllers;

use App\Models\GroupeDoublonsClient;
use App\Services\ClientDoublonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientDoublonController extends Controller
{
    public function __construct(
        private ClientDoublonService $doublonService
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $statut = $request->input('statut', '');
        $tri = $request->input('tri', 'recent');

        $this->doublonService->detecterEtCreerGroupes();

        $query = GroupeDoublonsClient::with([
            'clients' => fn ($q) => $q->with(['zone', 'commandes' => fn ($q2) => $q2->whereIn('status', ['validee', 'livree', 'a_preparer'])]),
            'principalClient',
        ]);

        if ($statut) {
            $query->where('statut', $statut);
        }

        $groupes = $query->get();

        if ($search) {
            $groupes = $groupes->filter(function ($g) use ($search) {
                return $g->clients->contains(fn ($c) =>
                    stripos($c->prenom . ' ' . $c->nom, $search) !== false
                    || stripos($c->tel ?? '', $search) !== false
                );
            })->values();
        }

        $groupes = match ($tri) {
            'commandes' => $groupes->sortByDesc(fn ($g) => $g->clients->sum(fn ($c) => $c->commandes->count())),
            'montant' => $groupes->sortByDesc(fn ($g) => $g->clients->sum(fn ($c) => $c->commandes->sum('prix_total'))),
            default => $groupes->sortByDesc('created_at'),
        };

        $stats = [
            'en_attente' => GroupeDoublonsClient::where('statut', 'en_attente')->count(),
            'verifies' => GroupeDoublonsClient::where('statut', 'verifie')->count(),
            'fusionnes' => GroupeDoublonsClient::where('statut', 'fusionne')->count(),
            'total_clients' => GroupeDoublonsClient::with('clients')->get()->sum(fn ($g) => $g->clients->count()),
        ];

        $groupesFormates = $groupes->values()->map(function ($g) {
            $clientsData = $g->clients->map(function ($c) {
                $cmdValides = $c->commandes->filter(fn ($cmd) => in_array($cmd->status, ['validee', 'livree', 'a_preparer']));
                $totalDepense = $cmdValides->sum('prix_total');
                $nbCommandes = $cmdValides->count();
                $derniereCmd = $cmdValides->sortByDesc('date')->first();

                return [
                    'id' => $c->id,
                    'nom' => $c->nom,
                    'prenom' => $c->prenom,
                    'tel' => $c->tel,
                    'adresse' => $c->adresse,
                    'zone' => $c->zone?->designation,
                    'nb_commandes' => $nbCommandes,
                    'total_depense' => $totalDepense,
                    'created_at' => $c->created_at?->format('d/m/Y'),
                    'derniere_commande' => $derniereCmd?->date?->format('d/m/Y'),
                    'is_principal' => $c->pivot->is_principal ?? ($c->id === $g->principal_client_id),
                ];
            });

            $totalCommandes = $clientsData->sum('nb_commandes');
            $totalDepense = $clientsData->sum('total_depense');

            $criteresLabels = [
                'nom_identique' => 'Nom identique',
                'adresse_identique' => 'Adresse identique',
                'meme_zone' => 'Même zone',
            ];
            $criteres = collect($g->criteres ?? [])->map(fn ($k) => $criteresLabels[$k] ?? $k)->values()->toArray();

            return [
                'id' => $g->id,
                'numero' => str_pad((string) $g->id, 3, '0', STR_PAD_LEFT),
                'statut' => $g->statut,
                'criteres' => $criteres,
                'clients' => $clientsData->values()->toArray(),
                'total_si_fusion' => [
                    'commandes' => $totalCommandes,
                    'montant' => $totalDepense,
                ],
            ];
        });

        return Inertia::render('Clients/Doublons', [
            'groupes' => $groupesFormates,
            'stats' => $stats,
            'filters' => $request->only(['search', 'statut', 'tri']),
        ]);
    }

    public function ignorer(GroupeDoublonsClient $groupe): RedirectResponse
    {
        $groupe->update(['statut' => 'ignore']);
        return redirect()->route('clients.doublons')->with('success', 'Groupe ignoré.');
    }

    public function verifier(GroupeDoublonsClient $groupe): RedirectResponse
    {
        $groupe->update(['statut' => 'verifie']);
        return redirect()->route('clients.doublons')->with('success', 'Groupe marqué comme vérifié.');
    }

    public function fusionner(Request $request, GroupeDoublonsClient $groupe): RedirectResponse
    {
        $ajouterTelSecondaire = filter_var($request->input('ajouter_tel_secondaire', false), FILTER_VALIDATE_BOOLEAN);
        $this->doublonService->fusionner($groupe, $ajouterTelSecondaire);
        return redirect()->route('clients.doublons')->with('success', 'Profils fusionnés avec succès.');
    }
}
