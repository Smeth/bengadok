<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientFrequence;
use App\Models\Commande;
use App\Services\ClientIndexService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function __construct(
        private ClientIndexService $clientIndexService,
    ) {}

    public function index(Request $request): Response
    {
        $result = $this->clientIndexService->paginatedIndex($request);

        return Inertia::render('Clients/Index', [
            'clients' => $result['clients'],
            'arrondissements' => $result['arrondissements'],
            'frequences' => $result['frequences'],
            'filters' => $request->only(['search', 'arrondissement', 'tri', 'frequence', 'frequence_id']),
        ]);
    }

    /**
     * Prospects : fiches où {@see Client::$promu_client_le} est encore null (aucune commande
     * encore marquée livrée — statut admin retiree — n'a déclenché la promotion en client).
     */
    public function prospects(Request $request): Response
    {
        $search = $request->input('search', '');
        $arrondissement = $request->input('arrondissement', '');
        $tri = $request->input('tri', 'recent');

        $baseQuery = Client::query()->whereNull('promu_client_le');

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'avec_commandes' => (clone $baseQuery)->has('commandes')->count(),
            'sans_commande' => (clone $baseQuery)->doesntHave('commandes')->count(),
            'eligibles_promotion' => (clone $baseQuery)
                ->whereHas('commandes', fn ($q) => $q->where('status', 'retiree'))
                ->count(),
        ];

        $query = (clone $baseQuery)
            ->withCount([
                'commandes',
                'commandes as commandes_reussies_count' => fn ($q) => $q->where('status', 'retiree'),
            ])
            ->withMax('commandes as derniere_commande_at', 'date');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('tel', 'like', "%{$search}%")
                    ->orWhere('tel_secondaire', 'like', "%{$search}%")
                    ->orWhere('adresse', 'like', "%{$search}%")
                    ->orWhere('arrondissement', 'like', "%{$search}%");
            });
        }

        if ($arrondissement !== '') {
            $query->where('arrondissement', $arrondissement);
        }

        match ($tri) {
            'nom' => $query->orderBy('prenom')->orderBy('nom'),
            'commandes' => $query->orderByDesc('commandes_count')->orderByDesc('updated_at'),
            default => $query->orderByDesc('updated_at'),
        };

        $prospects = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $c) => [
                'id' => $c->id,
                'nom' => $c->nom,
                'prenom' => $c->prenom,
                'tel' => $c->tel,
                'tel_secondaire' => $c->tel_secondaire,
                'adresse' => $c->adresse ?? '',
                'arrondissement' => $c->arrondissementAffiche(),
                'nb_commandes' => (int) $c->commandes_count,
                'nb_commandes_reussies' => (int) $c->commandes_reussies_count,
                'derniere_commande' => $c->derniere_commande_at
                    ? \Carbon\Carbon::parse($c->derniere_commande_at)->format('d/m/Y')
                    : null,
                'updated_at' => $c->updated_at?->format('d/m/Y H:i'),
                'statut' => $this->prospectStatut((int) $c->commandes_count, (int) $c->commandes_reussies_count),
            ]);

        return Inertia::render('Clients/Prospects', [
            'prospects' => $prospects,
            'arrondissements' => Client::ARRONDISSEMENTS,
            'stats' => $stats,
            'filters' => $request->only(['search', 'arrondissement', 'tri']),
        ]);
    }

    public function promouvoirClient(Client $client): RedirectResponse
    {
        if ($client->promu_client_le !== null) {
            return back()->with('error', 'Ce contact est déjà un client définitif.');
        }

        $aCommandeLivree = $client->commandes()->where('status', 'retiree')->exists();
        if (! $aCommandeLivree) {
            return back()->with(
                'error',
                'Promotion impossible : au moins une commande doit être livrée (statut « Livrée »).',
            );
        }

        $client->update(['promu_client_le' => now()]);

        return back()->with(
            'status',
            sprintf('%s est maintenant un client.', trim("{$client->prenom} {$client->nom}") ?: "Fiche #{$client->id}"),
        );
    }

    private function prospectStatut(int $nbCommandes, int $nbReussies): string
    {
        if ($nbCommandes === 0) {
            return 'sans_commande';
        }

        if ($nbReussies > 0) {
            return 'eligible_promotion';
        }

        return 'en_cours';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Commande>  $commandes
     */
    private function moyenneJoursEntreCommandes(\Illuminate\Support\Collection $commandes): ?float
    {
        $dates = $commandes->pluck('date')->filter()->map(fn ($d) => \Carbon\Carbon::parse($d))->sort()->values();
        if ($dates->count() < 2) {
            return null;
        }
        $sum = 0;
        for ($i = 1; $i < $dates->count(); $i++) {
            $sum += $dates[$i]->diffInDays($dates[$i - 1]);
        }

        return $sum / ($dates->count() - 1);
    }

    public function show(Client $client): Response
    {
        $commandes = $client->commandes()->whereIn('status', Commande::STATUTS_COMPTABILISES_CLIENT)->with('produits')->get();

        $totalDepense = $commandes->sum('prix_total');
        $nbCommandes = $commandes->count();
        $panierMoyen = $nbCommandes > 0 ? round($totalDepense / $nbCommandes, 0) : 0;
        $derniereCommande = $commandes->sortByDesc(function ($c) {
            $d = $c->date ?? $c->created_at;

            return $d ? $d->timestamp : 0;
        })->first();
        $clientDepuis = $client->client_depuis ?? $client->created_at;

        $pourSoi = $commandes->filter(fn ($c) => empty($c->beneficiaire) || $c->beneficiaire === 'Soi-même')->count();
        $pourTiers = $commandes->filter(fn ($c) => ! empty($c->beneficiaire) && $c->beneficiaire !== 'Soi-même')->count();
        $pctSoi = $nbCommandes > 0 ? round(($pourSoi / $nbCommandes) * 100) : 0;
        $pctTiers = $nbCommandes > 0 ? round(($pourTiers / $nbCommandes) * 100) : 0;

        $beneficiaires = $commandes->pluck('beneficiaire')->filter()->countBy();
        $tiersCountsHorsSoi = $beneficiaires
            ->filter(fn ($count, $label) => $label !== null && $label !== '' && $label !== 'Soi-même')
            ->sortDesc();
        $tiersFrequentKey = $tiersCountsHorsSoi->keys()->first();
        $tiersFrequent = $tiersFrequentKey
            ? (Client::BENEFICIAIRE_SHORT_LABELS[$tiersFrequentKey] ?? $tiersFrequentKey)
            : '-';
        $categoriesTiers = $beneficiaires
            ->keys()
            ->unique()
            ->filter(fn ($b) => $b && $b !== 'Soi-même')
            ->map(fn (string $b) => Client::BENEFICIAIRE_SHORT_LABELS[$b] ?? $b)
            ->values()
            ->toArray();

        $moyenneJours = $this->moyenneJoursEntreCommandes($commandes);
        $frequences = ClientFrequence::query()
            ->orderByDesc('priorite')
            ->orderBy('designation')
            ->get();
        $frequenceLabel = null;
        foreach ($frequences as $freq) {
            if ($freq->correspondAuxStats($nbCommandes, $moyenneJours)) {
                $frequenceLabel = $freq->designation;
                break;
            }
        }

        $medicamentsFrequents = DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
            ->where('commandes.client_id', $client->id)
            ->whereIn('commandes.status', Commande::STATUTS_STATS_VENTES)
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            })
            ->select(
                'produits.designation',
                DB::raw('SUM(COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)) as total'),
            )
            ->groupBy('produits.id', 'produits.designation')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->pluck('designation')
            ->toArray();

        $niches = $client->niches ?? [];
        $nichesLabels = collect($niches)
            ->map(fn (string $slug) => Client::NICHE_LABELS[$slug] ?? $slug)
            ->values()
            ->all();
        $canal = $client->canal_acquisition;
        $canalLabel = $canal ? (Client::CANAL_LABELS[$canal] ?? $canal) : null;

        return Inertia::render('Clients/Show', [
            'client' => [
                'id' => $client->id,
                'nom' => $client->nom,
                'prenom' => $client->prenom,
                'tel' => $client->tel,
                'tel_secondaire' => $client->tel_secondaire,
                'adresse' => $client->adresse,
                'arrondissement' => $client->arrondissementAffiche(),
                'client_depuis' => $clientDepuis?->format('d/m/Y'),
                'derniere_commande' => ($derniereCommande?->date ?? $derniereCommande?->created_at)?->format('d/m/Y'),
                'nb_commandes' => $nbCommandes,
                'total_depense' => $totalDepense,
                'panier_moyen' => $panierMoyen,
                'habitué' => $nbCommandes >= 5,
                'frequence_label' => $frequenceLabel,
                'pour_soi' => $pourSoi,
                'pour_tiers' => $pourTiers,
                'pct_soi' => $pctSoi,
                'pct_tiers' => $pctTiers,
                'categories_tiers' => $categoriesTiers,
                'tiers_frequent' => $tiersFrequent,
                'medicaments_frequents' => $medicamentsFrequents,
                'niches' => $niches,
                'niches_labels' => $nichesLabels,
                'canal_acquisition' => $canal,
                'canal_acquisition_label' => $canalLabel,
                'promu_client_le' => $client->promu_client_le?->format('d/m/Y'),
                'est_prospect' => $client->promu_client_le === null,
            ],
        ]);
    }

    public function updateEnrichissementProfil(Request $request, Client $client): RedirectResponse
    {
        $nicheKeys = array_keys(Client::NICHE_LABELS);
        $canalKeys = array_keys(Client::CANAL_LABELS);

        $validated = $request->validate([
            'niches' => ['nullable', 'array'],
            'niches.*' => ['string', Rule::in($nicheKeys)],
            'canal_acquisition' => ['nullable', 'string', Rule::in($canalKeys)],
        ]);

        $niches = array_values(array_unique($validated['niches'] ?? []));
        sort($niches);

        $client->update([
            'niches' => $niches === [] ? null : $niches,
            'canal_acquisition' => $validated['canal_acquisition'] ?? null,
        ]);

        return redirect()->route('clients.show', $client)->with('status', 'Profil enrichi.');
    }
}
