<?php

namespace App\Services;

use App\Events\UserNotificationsRefresh;
use App\Models\Commande;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BroadcastCommandeNotificationTargets
{
    private static int $silenceDepth = 0;

    private static bool $unavailableThisRequest = false;

    /** @var array<int, true> Au plus une diffusion par commande et par requête HTTP (sauf force). */
    private static array $dispatchedForCommandeIds = [];

    /**
     * Coupe les notifications temps réel (ex. import historique en lot).
     */
    public static function withoutBroadcasting(callable $callback): mixed
    {
        self::$silenceDepth++;

        try {
            return $callback();
        } finally {
            self::$silenceDepth--;
        }
    }

    public static function resetForTesting(): void
    {
        self::$silenceDepth = 0;
        self::$unavailableThisRequest = false;
        self::$dispatchedForCommandeIds = [];
    }

    /**
     * Après création / mise à jour d’une commande (événement Eloquent).
     *
     * @param  bool  $force  Relance même si déjà diffusé dans cette requête (ex. pièces jointes ordonnance).
     */
    public static function dispatchForCommande(Commande $commande, bool $force = false): void
    {
        $commandeId = (int) $commande->id;
        if ($commandeId === 0) {
            return;
        }

        if (! $force && isset(self::$dispatchedForCommandeIds[$commandeId])) {
            return;
        }

        self::$dispatchedForCommandeIds[$commandeId] = true;
        self::broadcastToUserIds(self::recipientUserIdsForCommande($commande));
    }

    /**
     * Après annulation groupée ou autre requête SQL sans événements Eloquent.
     *
     * @param  iterable<int, int|null>  $pharmacieIds
     */
    public static function dispatchForPharmacieIds(iterable $pharmacieIds): void
    {
        $ids = collect();

        foreach ($pharmacieIds as $pid) {
            if ($pid !== null && $pid !== 0) {
                $ids = $ids->merge(self::pharmacieUserIds((int) $pid));
            }
        }

        $ids = $ids->merge(self::backofficeUserIds())->unique()->values();

        self::broadcastToUserIds($ids);
    }

    /**
     * @return Collection<int, int>
     */
    private static function recipientUserIdsForCommande(Commande $commande): Collection
    {
        $ids = collect();

        if ($commande->pharmacie_id) {
            $ids = $ids->merge(self::pharmacieUserIds($commande->pharmacie_id));
        }

        return $ids->merge(self::backofficeUserIds())->unique()->values();
    }

    /**
     * @return Collection<int, int>
     */
    private static function pharmacieUserIds(int $pharmacieId): Collection
    {
        return User::query()
            ->where('pharmacie_id', $pharmacieId)
            ->role(['gerant', 'vendeur'])
            ->pluck('id');
    }

    /**
     * @return Collection<int, int>
     */
    private static function backofficeUserIds(): Collection
    {
        return User::role(['admin', 'super_admin', 'agent_call_center'])->pluck('id');
    }

    /**
     * @param  Collection<int, int|string>  $userIds
     */
    private static function broadcastToUserIds(Collection $userIds): void
    {
        if (self::$silenceDepth > 0 || self::$unavailableThisRequest) {
            return;
        }

        $driver = config('broadcasting.default');
        if ($driver === null || $driver === '' || $driver === 'null') {
            return;
        }

        foreach ($userIds as $userId) {
            try {
                broadcast(new UserNotificationsRefresh((int) $userId));
            } catch (BroadcastException $e) {
                self::markUnavailable((int) $userId, $e->getMessage());

                return;
            } catch (\Throwable $e) {
                self::markUnavailable((int) $userId, $e->getMessage());

                return;
            }
        }
    }

    private static function markUnavailable(int $userId, string $message): void
    {
        self::$unavailableThisRequest = true;
        Log::warning('Broadcast indisponible (notifications temps réel ignorées pour le reste de la requête).', [
            'user_id' => $userId,
            'message' => $message,
        ]);
    }
}
