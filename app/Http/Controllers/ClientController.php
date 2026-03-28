<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientFrequence;
use App\Models\Commande;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $zoneId = $request->input('zone_id', '');
        $tri = $request->input('tri', 'nom');
        $frequenceLegacy = $request->input('frequence', '');
        $frequenceId = $request->input('frequence_id', '');

        $statutsKpi = Commande::STATUTS_COMPTABILISES_CLIENT;

        $frequences = ClientFrequence::query()
            ->orderByDesc('priorite')
            ->orderBy('designation')
            ->get();

        if ($frequenceId === '' && $frequenceLegacy !== '') {
            if ($frequenceLegacy === 'habitué') {
                $frequenceId = (string) (ClientFrequence::query()->where('slug', 'habitue')->value('id') ?? '');
            } elseif ($frequenceLegacy === 'occasionnel') {
                $frequenceId = (string) (ClientFrequence::query()->where('slug', 'occasionnel')->value('id') ?? '');
            }
        }

        $query = Client::with([
            'zone',
            'commandes' => fn ($q) => $q->whereIn('status', $statutsKpi)->orderBy('date')->orderBy('created_at'),
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('tel', 'like', "%{$search}%")
                    ->orWhere('adresse', 'like', "%{$search}%");
            });
        }

        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        $clients = $query->get()->map(function ($c) use ($statutsKpi, $frequences) {
            $cmdComptees = $c->commandes->filter(fn ($cmd) => in_array($cmd->status, $statutsKpi, true));
            $totalDepense = (float) $cmdComptees->sum('prix_total');
            $nbCommandes = $cmdComptees->count();
            $panierMoyen = $nbCommandes > 0 ? round($totalDepense / $nbCommandes, 0) : 0;
            $moyenneJours = $this->moyenneJoursEntreCommandes($cmdComptees);

            $medicamentsFrequents = DB::table('commande_produit')
                ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
                ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
                ->where('commandes.client_id', $c->id)
                ->whereIn('commandes.status', $statutsKpi)
                ->select('produits.designation', DB::raw('SUM(commande_produit.quantite) as total'))
                ->groupBy('produits.id', 'produits.designation')
                ->orderByDesc('total')
                ->limit(3)
                ->get()
                ->pluck('designation')
                ->toArray();

            $frequenceLabel = null;
            foreach ($frequences as $freq) {
                if ($freq->correspondAuxStats($nbCommandes, $moyenneJours)) {
                    $frequenceLabel = $freq->designation;
                    break;
                }
            }

            return [
                'id' => $c->id,
                'nom' => $c->nom,
                'prenom' => $c->prenom,
                'tel' => $c->tel,
                'adresse' => $c->adresse,
                'zone' => $c->zone?->designation,
                'zone_id' => $c->zone_id,
                'nb_commandes' => $nbCommandes,
                'total_depense' => $totalDepense,
                'panier_moyen' => $panierMoyen,
                'medicaments_frequents' => $medicamentsFrequents,
                'habitué' => $nbCommandes >= 5,
                'frequence_label' => $frequenceLabel,
                '_moyenne_jours' => $moyenneJours,
                'created_at' => $c->created_at?->timestamp,
            ];
        });

        if ($frequenceId !== '') {
            $freq = $frequences->firstWhere('id', (int) $frequenceId);
            if ($freq) {
                $clients = $clients->filter(fn ($row) => $freq->correspondAuxStats(
                    $row['nb_commandes'],
                    $row['_moyenne_jours']
                ))->map(function ($row) {
                    unset($row['_moyenne_jours']);

                    return $row;
                })->values();
            }
        } else {
            $clients = $clients->map(function ($row) {
                unset($row['_moyenne_jours']);

                return $row;
            });
        }

        $clients = match ($tri) {
            'commandes' => $clients->sortByDesc('nb_commandes')->values(),
            'depense' => $clients->sortByDesc('total_depense')->values(),
            'recent' => $clients->sortByDesc(fn ($c) => $c['created_at'] ?? 0)->values(),
            default => $clients->sortBy(fn ($c) => ($c['prenom'] ?? '').' '.($c['nom'] ?? ''))->values(),
        };

        $zones = Zone::orderBy('designation')->get(['id', 'designation']);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'zones' => $zones,
            'frequences' => $frequences->map(fn (ClientFrequence $f) => [
                'id' => $f->id,
                'designation' => $f->designation,
                'slug' => $f->slug,
                'commandes_minimum' => $f->commandes_minimum,
                'commandes_maximum' => $f->commandes_maximum,
                'intervalle_max_jours' => $f->intervalle_max_jours,
                'priorite' => $f->priorite,
            ])->values(),
            'filters' => $request->only(['search', 'zone_id', 'tri', 'frequence', 'frequence_id']),
        ]);
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
        $client->load('zone');
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
            ->whereIn('commandes.status', Commande::STATUTS_COMPTABILISES_CLIENT)
            ->select('produits.designation', DB::raw('SUM(commande_produit.quantite) as total'))
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
                'zone' => $client->zone?->designation,
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
