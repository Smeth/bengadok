<?php

namespace App\Http\Middleware;

use App\Models\Commande;
use App\Models\MotifAnnulation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            /** Jeton CSRF à jour à chaque réponse Inertia (POST /logout, formulaires, etc.) */
            'csrf_token' => fn () => csrf_token(),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
                'createdUsername' => fn () => $request->session()->get('createdUsername'),
            ],
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'roles' => $user->getRoleNames()->toArray(),
                    'pharmacie' => $user->pharmacie ? [
                        'id' => $user->pharmacie->id,
                        'designation' => $user->pharmacie->designation,
                    ] : null,
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'notifications' => fn () => $this->getNotifications($request),
            /** Motifs d'annulation (liste triée : slug, label, autorise_relance) */
            'motifs_annulation' => fn () => MotifAnnulation::orderedForShare(),
        ];
    }

    /**
     * Récupère les notifications selon le rôle de l'utilisateur.
     * - Pharmacie (gerant, vendeur) : nouvelles commandes assignées
     * - Backoffice (admin, agent) : commandes validées envoyées par les pharmacies
     */
    private function getNotifications(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return ['count' => 0, 'items' => []];
        }

        $roles = $user->getRoleNames()->toArray();
        $isPharmacie = in_array('gerant', $roles) || in_array('vendeur', $roles);
        $isBackoffice = in_array('admin', $roles) || in_array('agent_call_center', $roles) || in_array('super_admin', $roles);

        $query = Commande::query();

        if ($isPharmacie && $user->pharmacie_id) {
            // Pharmacie : nouvelles commandes — pas de chargement client (données sensibles, non exposées au front)
            $query->where('pharmacie_id', $user->pharmacie_id)
                ->where('status_pharmacie', 'nouvelle');
        } elseif ($isBackoffice) {
            // Backoffice : commandes en attente de validation admin
            $query->with(['client:id,nom,prenom', 'pharmacie:id,designation'])
                ->where('status', 'en_attente')
                ->where('updated_at', '>=', now()->subDays(3));
        } else {
            return ['count' => 0, 'items' => []];
        }

        $count = (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'numero', 'status', 'status_pharmacie', 'client_id', 'pharmacie_id', 'created_at', 'updated_at'])
            ->map(function (Commande $c) use ($isPharmacie) {
                return [
                    'id' => $c->id,
                    'numero' => $c->numero,
                    'status' => $c->status,
                    'status_pharmacie' => $c->status_pharmacie,
                    'status_label' => Commande::STATUSES[$c->status] ?? $c->status,
                    'client' => $isPharmacie ? null : ($c->client ? ['nom' => $c->client->nom, 'prenom' => $c->client->prenom] : null),
                    'pharmacie' => $isPharmacie ? null : ($c->pharmacie ? ['designation' => $c->pharmacie->designation] : null),
                    'created_at' => $c->created_at?->toIso8601String(),
                    'url' => $this->notificationCommandesListUrl($c, $isPharmacie),
                ];
            })
            ->values()
            ->toArray();

        return ['count' => $count, 'items' => $items];
    }

    /**
     * Cible liste commandes + onglet / filtre statut aligné sur la commande (pharmacie : DokPharma, admin : /commandes).
     */
    private function notificationCommandesListUrl(Commande $c, bool $isPharmacie): string
    {
        if ($isPharmacie) {
            $onglet = match ($c->status_pharmacie) {
                'nouvelle' => 'nouvelles',
                'attente_confirmation', 'indisponible' => 'en_attente',
                'valide_a_preparer' => 'a_preparer',
                'livre' => 'livrees',
                default => 'nouvelles',
            };

            return '/dok-pharma/commandes?onglet='.$onglet;
        }

        $status = $c->status ?? 'nouvelle';
        $filter = in_array($status, ['validee', 'a_preparer'], true) ? 'validee' : $status;

        return '/commandes?status='.rawurlencode($filter);
    }
}
