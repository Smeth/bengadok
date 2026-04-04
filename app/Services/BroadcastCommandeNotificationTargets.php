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
    /**
     * Après création / mise à jour d’une commande (événement Eloquent).
     */
    public static function dispatchForCommande(Commande $commande): void
    {
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
        $driver = config('broadcasting.default');
        if ($driver === null || $driver === '' || $driver === 'null') {
            return;
        }

        foreach ($userIds as $userId) {
            try {
                broadcast(new UserNotificationsRefresh((int) $userId));
            } catch (BroadcastException $e) {
                Log::warning('Broadcast indisponible (notifications temps réel ignorées).', [
                    'user_id' => $userId,
                    'message' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Échec broadcast (notifications temps réel ignorées).', [
                    'user_id' => $userId,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
