<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\CommandePieceJointe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DokPharmaCommandeIndexService
{
    public function __construct(
        private CommandeDateFormatter $commandeDateFormatter,
    ) {}

    /**
     * @return array{
     *     commandes: LengthAwarePaginator<int, array<string, mixed>>|array{data: array{}, links: array{}, current_page: int, last_page: int, from: int, to: int, total: int},
     *     stats: array{nouvelles: int, en_attente: int, a_preparer: int, livrees: int},
     *     onglet: string,
     *     search: string,
     *     canViewHistorique: bool
     * }
     */
    public function paginatedIndex(Request $request): array
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        $canViewHistorique = ! $this->userIsVendeurSeul($request);

        if (! $pharmacieId) {
            return [
                'commandes' => [
                    'data' => [], 'links' => [], 'current_page' => 1,
                    'last_page' => 1, 'from' => 0, 'to' => 0, 'total' => 0,
                ],
                'stats' => ['nouvelles' => 0, 'en_attente' => 0, 'a_preparer' => 0, 'livrees' => 0],
                'onglet' => $request->input('onglet', 'nouvelles'),
                'search' => Str::limit(trim((string) $request->input('search', '')), 100, ''),
                'canViewHistorique' => $canViewHistorique,
            ];
        }

        $onglet = $request->input('onglet', 'nouvelles');
        $search = Str::limit(trim((string) $request->input('search', '')), 100, '');

        $query = Commande::with(['client', 'produits', 'ordonnance', 'piecesJointes.uploadedBy'])
            ->where('pharmacie_id', $pharmacieId);

        $query
            ->when($onglet === 'nouvelles', fn ($q) => $q->where('status_pharmacie', 'nouvelle'))
            ->when($onglet === 'en_attente', fn ($q) => $q->whereIn('status_pharmacie', ['attente_confirmation', 'indisponible']))
            ->when($onglet === 'a_preparer', fn ($q) => $q->where('status_pharmacie', 'valide_a_preparer'))
            ->when($onglet === 'livrees', fn ($q) => $q->where('status_pharmacie', 'livre'))
            ->when(! in_array($onglet, ['nouvelles', 'en_attente', 'a_preparer', 'livrees']),
                fn ($q) => $q->where('status_pharmacie', 'nouvelle'));

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('numero', 'like', $like)
                    ->orWhereHas('client', function ($cq) use ($like) {
                        $cq->where('nom', 'like', $like)
                            ->orWhere('prenom', 'like', $like);
                    })
                    ->orWhereHas('produits', function ($pq) use ($like) {
                        $pq->where('designation', 'like', $like);
                    });
            });
        }

        $commandes = $query
            ->latest('date')
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($c) => $this->mapCommandeRow($c));

        $stats = $this->statsForPharmacie($pharmacieId);

        return [
            'commandes' => $commandes,
            'stats' => $stats,
            'onglet' => $onglet,
            'search' => $search,
            'canViewHistorique' => $canViewHistorique,
        ];
    }

    public function userIsVendeurSeul(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $user->hasRole('vendeur')
            && ! $user->hasRole('gerant');
    }

    /**
     * @return array{nouvelles: int, en_attente: int, a_preparer: int, livrees: int}
     */
    private function statsForPharmacie(int $pharmacieId): array
    {
        $counts = Commande::query()
            ->where('pharmacie_id', $pharmacieId)
            ->selectRaw('status_pharmacie, COUNT(*) as total')
            ->groupBy('status_pharmacie')
            ->pluck('total', 'status_pharmacie');

        return [
            'nouvelles' => (int) ($counts['nouvelle'] ?? 0),
            'en_attente' => (int) (($counts['attente_confirmation'] ?? 0) + ($counts['indisponible'] ?? 0)),
            'a_preparer' => (int) ($counts['valide_a_preparer'] ?? 0),
            'livrees' => (int) ($counts['livre'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCommandeRow(Commande $c): array
    {
        return [
            'id' => $c->id,
            'numero' => $c->numero,
            'date' => $this->commandeDateFormatter->formatDateHeure($c),
            'status' => $c->status,
            'status_pharmacie' => $c->status_pharmacie,
            'client' => $c->client
                ? [
                    'nom' => $c->client->nom,
                    'prenom' => $c->client->prenom,
                    'sexe' => $c->client->sexe,
                ]
                : null,
            'produits' => $c->produits->map(fn ($p) => [
                'id' => $p->id,
                'designation' => $p->designation,
                'pivot' => [
                    'quantite' => $p->pivot->quantite,
                    'prix_unitaire' => (float) ($p->pivot->prix_unitaire ?? 0),
                    'status' => $p->pivot->status ?? 'en_attente',
                    'quantite_confirmee' => $p->pivot->quantite_confirmee ?? null,
                    'vente_libre' => (bool) ($p->pivot->vente_libre ?? false),
                ],
            ])->values(),
            'ordonnance_id' => $c->ordonnance_id,
            'ordonnance_url' => $c->ordonnance?->file_url,
            'ordonnance_is_pdf' => (bool) ($c->ordonnance?->is_pdf ?? false),
            'commentaire' => $c->commentaire,
            'commentaire_pharmacie' => $c->commentaire_pharmacie,
            'prix_medicaments' => (float) ($c->prix_medicaments ?? 0),
            'pieces_jointes' => $c->piecesJointes
                ->map(fn (CommandePieceJointe $pj) => $pj->toFrontendArray())
                ->values()
                ->all(),
        ];
    }
}
