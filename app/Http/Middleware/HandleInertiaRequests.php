<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use App\Models\Commande;
use App\Models\MotifAnnulation;
use App\Models\OrdonnanceVerificationSetting;
use App\Services\PharmacyDataResetService;
use Illuminate\Http\Request;
use Inertia\Inertia;
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
            /**
             * Inclus même lors des rechargements partiels (`only`), sinon le méta-tag et Axios
             * gardent un jeton périmé → erreurs 419 aléatoires.
             *
             * @see https://inertiajs.com/shared-data#merging-shared-data
             */
            'csrf_token' => Inertia::always(fn () => csrf_token()),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'createdUsername' => fn () => $request->session()->get('createdUsername'),
                'createdPassword' => fn () => $request->session()->get('createdPassword'),
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
            /** Compteurs commandes pharmacie (alertes temps réel hors page commandes). */
            'pharmacyStats' => fn () => $this->getPharmacyStats($request),
            /** Compteurs commandes back-office (alertes temps réel hors page commandes). */
            'backofficeStats' => fn () => $this->getBackofficeStats($request),
            /** Motifs d'annulation (liste triée : slug, label, autorise_relance) */
            'motifs_annulation' => fn () => MotifAnnulation::orderedForShare(),
            /** Délai (heures) avant de pouvoir resélectionner la même pharmacie lors d'une relance */
            'delai_relance_meme_pharmacie_heures' => fn () => AppSetting::delaiRelanceMemePharmacieHeures(),
            /** OCR / règles ordonnance : mode d’exécution pour l’UI (barre d’attente, etc.) */
            'ordonnanceVerificationSettings' => function () {
                $row = OrdonnanceVerificationSetting::query()->first();

                return [
                    'enabled' => $row?->enabled ?? true,
                    'execution_mode' => $row?->execution_mode
                        ?? OrdonnanceVerificationSetting::EXECUTION_MODE_QUEUE,
                ];
            },
            /** Réinitialisations destructives (page /settings/reset) */
            'allowPharmacyReset' => fn () => PharmacyDataResetService::isAllowed(),
            /** Réinitialisation « base neuve » (local uniquement) */
            'allowLocalAppReset' => fn () => config('app.env') === 'local',
        ];
    }

    /**
     * Compteurs commandes pour l’espace pharmacie (partagés sur toutes les pages).
     *
     * @return array{nouvelles: int, a_preparer: int}|null
     */
    private function getPharmacyStats(Request $request): ?array
    {
        $user = $request->user();
        if (! $user || ! $user->pharmacie_id) {
            return null;
        }

        $roles = $user->getRoleNames()->toArray();
        if (! in_array('gerant', $roles, true) && ! in_array('vendeur', $roles, true)) {
            return null;
        }

        $pharmacieId = $user->pharmacie_id;

        return [
            'nouvelles' => Commande::query()
                ->where('pharmacie_id', $pharmacieId)
                ->where('status_pharmacie', 'nouvelle')
                ->count(),
            'a_preparer' => Commande::query()
                ->where('pharmacie_id', $pharmacieId)
                ->where('status_pharmacie', 'valide_a_preparer')
                ->count(),
        ];
    }

    /**
     * Compteurs commandes pour le back-office (partagés sur toutes les pages).
     *
     * @return array{en_attente: int, nouvelles: int}|null
     */
    private function getBackofficeStats(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $roles = $user->getRoleNames()->toArray();
        if (
            ! in_array('admin', $roles, true)
            && ! in_array('super_admin', $roles, true)
            && ! in_array('agent_call_center', $roles, true)
        ) {
            return null;
        }

        return [
            'en_attente' => Commande::query()
                ->where('status', 'en_attente')
                ->count(),
            'nouvelles' => Commande::query()
                ->where('status', 'nouvelle')
                ->count(),
        ];
    }

    /**
     * Récupère les notifications selon le rôle de l'utilisateur.
     * - Pharmacie (gerant, vendeur) : nouvelles commandes + commandes à préparer
     * - Backoffice (admin, agent) : retours pharmacie (en attente) + nouvelles commandes
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

        if ($isPharmacie && $user->pharmacie_id) {
            $pharmacieId = $user->pharmacie_id;

            $countNouvelles = Commande::query()
                ->where('pharmacie_id', $pharmacieId)
                ->where('status_pharmacie', 'nouvelle')
                ->count();

            $countAPreparer = Commande::query()
                ->where('pharmacie_id', $pharmacieId)
                ->where('status_pharmacie', 'valide_a_preparer')
                ->count();

            $items = Commande::query()
                ->with(['client:id,nom,prenom'])
                ->where('pharmacie_id', $pharmacieId)
                ->whereIn('status_pharmacie', ['nouvelle', 'valide_a_preparer'])
                ->orderByRaw("CASE WHEN status_pharmacie = 'nouvelle' THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(['id', 'numero', 'status', 'status_pharmacie', 'client_id', 'pharmacie_id', 'beneficiaire', 'created_at', 'updated_at'])
                ->map(function (Commande $c) {
                    $alertKind = $c->status_pharmacie === 'valide_a_preparer'
                        ? 'a_preparer'
                        : 'nouvelle';

                    return [
                        'id' => $c->id,
                        'numero' => $c->numero,
                        'status' => $c->status,
                        'status_pharmacie' => $c->status_pharmacie,
                        'alert_kind' => $alertKind,
                        'status_label' => $alertKind === 'a_preparer'
                            ? 'À préparer'
                            : 'Nouvelle commande',
                        'client' => $c->client ? ['nom' => $c->client->nom, 'prenom' => $c->client->prenom] : null,
                        'beneficiaire' => $c->beneficiaire,
                        'pharmacie' => null,
                        'created_at' => $c->created_at?->toIso8601String(),
                        'url' => $this->notificationCommandesListUrl($c, true),
                    ];
                })
                ->values()
                ->toArray();

            return [
                'count' => $countNouvelles + $countAPreparer,
                'count_nouvelles' => $countNouvelles,
                'count_a_preparer' => $countAPreparer,
                'items' => $items,
            ];
        } elseif ($isBackoffice) {
            $countEnAttente = Commande::query()
                ->where('status', 'en_attente')
                ->where('updated_at', '>=', now()->subDays(3))
                ->count();

            $countNouvelles = Commande::query()
                ->where('status', 'nouvelle')
                ->count();

            $items = Commande::query()
                ->with(['client:id,nom,prenom', 'pharmacie:id,designation'])
                ->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('status', 'en_attente')
                            ->where('updated_at', '>=', now()->subDays(3));
                    })->orWhere('status', 'nouvelle');
                })
                ->orderByRaw("CASE WHEN status = 'en_attente' THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(['id', 'numero', 'status', 'status_pharmacie', 'client_id', 'pharmacie_id', 'beneficiaire', 'created_at', 'updated_at'])
                ->map(function (Commande $c) {
                    $alertKind = $c->status === 'nouvelle' ? 'nouvelle' : 'en_attente';

                    return [
                        'id' => $c->id,
                        'numero' => $c->numero,
                        'status' => $c->status,
                        'status_pharmacie' => $c->status_pharmacie,
                        'alert_kind' => $alertKind,
                        'status_label' => $alertKind === 'en_attente'
                            ? 'En attente validation'
                            : 'Nouvelle commande',
                        'client' => $c->client ? ['nom' => $c->client->nom, 'prenom' => $c->client->prenom] : null,
                        'beneficiaire' => $c->beneficiaire,
                        'pharmacie' => $c->pharmacie ? ['designation' => $c->pharmacie->designation] : null,
                        'created_at' => $c->created_at?->toIso8601String(),
                        'url' => $this->notificationCommandesListUrl($c, false),
                    ];
                })
                ->values()
                ->toArray();

            return [
                'count' => $countEnAttente + $countNouvelles,
                'count_en_attente' => $countEnAttente,
                'count_nouvelles' => $countNouvelles,
                'items' => $items,
            ];
        } else {
            return ['count' => 0, 'items' => []];
        }
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
