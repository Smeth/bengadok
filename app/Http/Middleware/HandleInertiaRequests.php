<?php

namespace App\Http\Middleware;

use App\Models\Commande;
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
                    'pharmacie' => $user->pharmacie,
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'notifications' => fn () => $this->getNotifications($request),
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

        $query = Commande::query()->with(['client:id,nom,prenom', 'pharmacie:id,designation']);

        if ($isPharmacie && $user->pharmacie_id) {
            $query->where('pharmacie_id', $user->pharmacie_id)
                ->whereIn('status', ['nouvelle', 'en_attente']);
        } elseif ($isBackoffice) {
            $query->whereIn('status', ['validee', 'partiellement_validee'])
                ->where('updated_at', '>=', now()->subDays(3));
        } else {
            return ['count' => 0, 'items' => []];
        }

        $count = (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'numero', 'status', 'client_id', 'pharmacie_id', 'created_at', 'updated_at'])
            ->map(fn (Commande $c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'status' => $c->status,
                'status_label' => Commande::STATUSES[$c->status] ?? $c->status,
                'client' => $c->client ? ['nom' => $c->client->nom, 'prenom' => $c->client->prenom] : null,
                'pharmacie' => $c->pharmacie ? ['designation' => $c->pharmacie->designation] : null,
                'created_at' => $c->created_at?->toIso8601String(),
                'url' => '/commandes/' . $c->id,
            ])
            ->values()
            ->toArray();

        return ['count' => $count, 'items' => $items];
    }
}
