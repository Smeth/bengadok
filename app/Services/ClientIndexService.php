<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientFrequence;
use App\Models\Commande;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientIndexService
{
    /**
     * @return array{
     *     clients: LengthAwarePaginator<int, array<string, mixed>>,
     *     arrondissements: list<string>,
     *     frequences: Collection<int, array<string, mixed>>
     * }
     */
    public function paginatedIndex(Request $request): array
    {
        $search = (string) $request->input('search', '');
        $arrondissement = (string) $request->input('arrondissement', '');
        $tri = (string) $request->input('tri', 'nom');
        $frequenceLegacy = (string) $request->input('frequence', '');
        $frequenceId = (string) $request->input('frequence_id', '');

        $statutsKpi = Commande::STATUT_PHARMACIE_CA_COMPTABILISE;

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

        $statsSub = DB::table('commandes')
            ->select('client_id')
            ->selectRaw('COUNT(*) as nb_commandes')
            ->selectRaw('COALESCE(SUM(prix_total), 0) as total_depense')
            ->where('status_pharmacie', $statutsKpi)
            ->where('status', '<>', 'annulee')
            ->groupBy('client_id');

        $intervalSub = DB::query()
            ->fromSub(function ($q) use ($statutsKpi) {
                $q->from('commandes')
                    ->select('client_id', 'date')
                    ->selectRaw(
                        'DATEDIFF(`date`, LAG(`date`) OVER (PARTITION BY client_id ORDER BY `date`, created_at)) as diff_jours'
                    )
                    ->where('status_pharmacie', $statutsKpi)
                    ->where('status', '<>', 'annulee')
                    ->whereNotNull('date');
            }, 'ordered')
            ->select('client_id')
            ->selectRaw('AVG(diff_jours) as moyenne_jours')
            ->whereNotNull('diff_jours')
            ->groupBy('client_id');

        $query = Client::query()
            ->whereNotNull('promu_client_le')
            ->leftJoinSub($statsSub, 'cmd_stats', 'cmd_stats.client_id', '=', 'clients.id')
            ->leftJoinSub($intervalSub, 'cmd_interval', 'cmd_interval.client_id', '=', 'clients.id')
            ->select([
                'clients.*',
                DB::raw('COALESCE(cmd_stats.nb_commandes, 0) as nb_commandes'),
                DB::raw('COALESCE(cmd_stats.total_depense, 0) as total_depense'),
                'cmd_interval.moyenne_jours',
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('clients.nom', 'like', "%{$search}%")
                    ->orWhere('clients.prenom', 'like', "%{$search}%")
                    ->orWhere('clients.tel', 'like', "%{$search}%")
                    ->orWhere('clients.adresse', 'like', "%{$search}%")
                    ->orWhere('clients.arrondissement', 'like', "%{$search}%");
            });
        }

        if ($arrondissement !== '') {
            $query->where('clients.arrondissement', $arrondissement);
        }

        if ($frequenceId !== '') {
            $freq = $frequences->firstWhere('id', (int) $frequenceId);
            if ($freq) {
                $this->applyFrequenceFilter($query, $freq);
            }
        }

        match ($tri) {
            'commandes' => $query->orderByDesc('nb_commandes')->orderBy('clients.prenom')->orderBy('clients.nom'),
            'depense' => $query->orderByDesc('total_depense')->orderBy('clients.prenom')->orderBy('clients.nom'),
            'recent' => $query->orderByDesc('clients.created_at'),
            default => $query->orderBy('clients.prenom')->orderBy('clients.nom'),
        };

        $paginator = $query
            ->paginate(15)
            ->withQueryString();

        $clientIds = collect($paginator->items())->pluck('id')->all();
        $medicamentsParClient = $this->medicamentsFrequentsPourClients($clientIds);

        $paginator->through(function (Client $c) use ($frequences, $medicamentsParClient) {
            $nbCommandes = (int) $c->nb_commandes;
            $totalDepense = (float) $c->total_depense;
            $panierMoyen = $nbCommandes > 0 ? (int) round($totalDepense / $nbCommandes, 0) : 0;
            $moyenneJours = $c->moyenne_jours !== null ? (float) $c->moyenne_jours : null;

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
                'arrondissement' => $c->arrondissementAffiche(),
                'nb_commandes' => $nbCommandes,
                'total_depense' => $totalDepense,
                'panier_moyen' => $panierMoyen,
                'medicaments_frequents' => $medicamentsParClient[$c->id] ?? [],
                'habitué' => $nbCommandes >= 5,
                'frequence_label' => $frequenceLabel,
            ];
        });

        return [
            'clients' => $paginator,
            'arrondissements' => Client::ARRONDISSEMENTS,
            'frequences' => $frequences->map(fn (ClientFrequence $f) => [
                'id' => $f->id,
                'designation' => $f->designation,
                'slug' => $f->slug,
                'commandes_minimum' => $f->commandes_minimum,
                'commandes_maximum' => $f->commandes_maximum,
                'intervalle_max_jours' => $f->intervalle_max_jours,
                'priorite' => $f->priorite,
            ])->values(),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Client>  $query
     */
    private function applyFrequenceFilter($query, ClientFrequence $freq): void
    {
        $query->whereRaw('COALESCE(cmd_stats.nb_commandes, 0) >= ?', [$freq->commandes_minimum]);

        if ($freq->commandes_maximum !== null) {
            $query->whereRaw('COALESCE(cmd_stats.nb_commandes, 0) <= ?', [$freq->commandes_maximum]);
        }

        if ($freq->intervalle_max_jours !== null) {
            $query->whereNotNull('cmd_interval.moyenne_jours')
                ->where('cmd_interval.moyenne_jours', '<=', $freq->intervalle_max_jours);
        }
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, list<string>>
     */
    private function medicamentsFrequentsPourClients(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $rows = DB::table('commande_produit')
            ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
            ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
            ->whereIn('commandes.client_id', $clientIds)
            ->tap(fn ($q) => Commande::applyVentesComptabilisees($q, 'commandes'))
            ->where(function ($q) {
                $q->whereNull('commande_produit.status')
                    ->orWhere('commande_produit.status', '<>', 'indisponible');
            })
            ->select(
                'commandes.client_id',
                'produits.designation',
                DB::raw('SUM(COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)) as total'),
            )
            ->groupBy('commandes.client_id', 'produits.id', 'produits.designation')
            ->orderByDesc('total')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $cid = (int) $row->client_id;
            if (! isset($grouped[$cid])) {
                $grouped[$cid] = [];
            }
            if (count($grouped[$cid]) < 3) {
                $grouped[$cid][] = $row->designation;
            }
        }

        return $grouped;
    }
}
