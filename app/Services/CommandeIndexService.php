<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\User;
use App\Support\CommandeMedicamentsResume;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CommandeIndexService
{
    /**
     * @return array{
     *     commandes: LengthAwarePaginator<int, array<string, mixed>>,
     *     stats: array{nouvelles: int, en_attente: int, validees: int, livrees: int, annulees: int},
     *     filters: array{search?: string, status?: string, periode?: string, date?: string},
     *     openDetailCommandeId: int|null,
     *     canManageCommandes?: bool
     * }
     */
    public function paginatedIndex(User $user, Request $request): array
    {
        $query = $this->baseQuery($user)->with([
            'client:id,nom,prenom,tel,adresse,sexe',
            'produits' => fn ($q) => $q->select('produits.id', 'produits.designation', 'produits.dosage'),
        ]);

        $this->applySearch($query, $request->input('search'));

        if ($status = $request->input('status')) {
            if ($status === 'validee') {
                $query->whereIn('status', ['validee', 'a_preparer']);
            } else {
                $query->where('status', $status);
            }
        }

        $this->applyTemporalFilters($query, $request);

        $commandes = $query
            ->orderByRaw('COALESCE(commandes.date, DATE(commandes.created_at)) DESC')
            ->orderByDesc('commandes.created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Commande $commande): array => $this->mapListRow($commande));

        $statsBase = $this->baseQuery($user);
        $this->applySearch($statsBase, $request->input('search'));
        $this->applyTemporalFilters($statsBase, $request);

        $statsRow = (clone $statsBase)->selectRaw("
            SUM(CASE WHEN status = 'nouvelle' THEN 1 ELSE 0 END) as nouvelles,
            SUM(CASE WHEN status = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
            SUM(CASE WHEN status IN ('validee', 'a_preparer') THEN 1 ELSE 0 END) as validees,
            SUM(CASE WHEN status = 'retiree' THEN 1 ELSE 0 END) as livrees,
            SUM(CASE WHEN status = 'annulee' THEN 1 ELSE 0 END) as annulees
        ")->first();

        $canManageCommandes = $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center']);

        $payload = [
            'commandes' => $commandes,
            'stats' => [
                'nouvelles' => (int) ($statsRow->nouvelles ?? 0),
                'en_attente' => (int) ($statsRow->en_attente ?? 0),
                'validees' => (int) ($statsRow->validees ?? 0),
                'livrees' => (int) ($statsRow->livrees ?? 0),
                'annulees' => (int) ($statsRow->annulees ?? 0),
            ],
            'filters' => $request->only(['search', 'status', 'periode', 'date']),
            'openDetailCommandeId' => $request->filled('detail') ? $request->integer('detail') : null,
        ];

        if ($canManageCommandes) {
            $payload['canManageCommandes'] = true;
        }

        return $payload;
    }

    /**
     * Payload minimal pour la liste Inertia (sans relations produits complètes).
     *
     * @return array<string, mixed>
     */
    private function mapListRow(Commande $commande): array
    {
        $client = $commande->client;

        return [
            'id' => $commande->id,
            'numero' => $commande->numero,
            'date' => $commande->date,
            'status' => $commande->status,
            'prix_total' => (float) $commande->prix_total,
            'medicaments_resume' => CommandeMedicamentsResume::fromCollection($commande->produits),
            'client' => $client ? [
                'nom' => $client->nom,
                'prenom' => $client->prenom,
                'tel' => $client->tel,
                'adresse' => $client->adresse,
                'sexe' => $client->sexe,
            ] : [
                'nom' => '',
                'prenom' => '',
                'tel' => '',
            ],
        ];
    }

    private function baseQuery(User $user): Builder
    {
        return Commande::query()
            ->when($user->pharmacie_id, fn ($q) => $q->where('pharmacie_id', $user->pharmacie_id))
            ->when($user->hasAnyRole(['admin', 'super_admin', 'agent_call_center']), fn ($q) => $q->whereNull('parent_id'));
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->where('numero', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($q) => $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('tel', 'like', "%{$search}%"))
                ->orWhereHas('produits', fn ($q) => $q->where('designation', 'like', "%{$search}%"));
        });
    }

    /**
     * Date exacte (query) prioritaire sur période. Date effective = date commande ou jour de création si null.
     */
    private function applyTemporalFilters(Builder $query, Request $request): void
    {
        $date = $request->input('date');
        $periode = $request->input('periode');

        if ($date) {
            $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) = ?', [$date]);

            return;
        }

        if (! $periode) {
            return;
        }

        match ($periode) {
            'aujourdhui' => $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) = ?', [now()->toDateString()]),
            'semaine' => $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) >= ?', [now()->copy()->startOfWeek()->toDateString()]),
            'mois' => $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) >= ?', [now()->copy()->startOfMonth()->toDateString()]),
            default => null,
        };
    }
}
