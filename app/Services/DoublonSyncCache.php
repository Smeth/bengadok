<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

final class DoublonSyncCache
{
    public const CLIENT_SYNC_LOCK = 'doublons:clients:sync_lock';

    public const CLIENT_LAST_SYNC = 'doublons:clients:last_sync';

    public const MEDICAMENT_SYNC_LOCK_PREFIX = 'doublons:medicaments:sync_lock:';

    public const MEDICAMENT_LAST_SYNC_PREFIX = 'doublons:medicaments:last_sync:';

    public const MEDICAMENT_STALE = 'doublons:medicaments:stale';

    public const TTL_SECONDS = 3600;

    public static function shouldQueueClientSync(): bool
    {
        if (Cache::has(self::CLIENT_SYNC_LOCK)) {
            return false;
        }

        $last = Cache::get(self::CLIENT_LAST_SYNC);

        return $last === null || now()->diffInSeconds($last) >= self::TTL_SECONDS;
    }

    public static function markClientSyncQueued(): void
    {
        Cache::put(self::CLIENT_SYNC_LOCK, true, self::TTL_SECONDS);
    }

    public static function markClientSyncCompleted(): void
    {
        Cache::forget(self::CLIENT_SYNC_LOCK);
        Cache::put(self::CLIENT_LAST_SYNC, now(), self::TTL_SECONDS);
    }

    public static function invalidateClientSync(): void
    {
        Cache::forget(self::CLIENT_SYNC_LOCK);
        Cache::forget(self::CLIENT_LAST_SYNC);
    }

    public static function medicamentCriteriaKey(array $criteresActifs): string
    {
        $sorted = $criteresActifs;
        sort($sorted);

        return md5(implode(',', $sorted));
    }

    public static function shouldQueueMedicamentSync(string $criteriaKey): bool
    {
        $lockKey = self::MEDICAMENT_SYNC_LOCK_PREFIX.$criteriaKey;
        if (Cache::has($lockKey)) {
            return false;
        }

        if (Cache::get(self::MEDICAMENT_STALE)) {
            return true;
        }

        $last = Cache::get(self::MEDICAMENT_LAST_SYNC_PREFIX.$criteriaKey);

        return $last === null || now()->diffInSeconds($last) >= self::TTL_SECONDS;
    }

    public static function markMedicamentSyncQueued(string $criteriaKey): void
    {
        Cache::put(self::MEDICAMENT_SYNC_LOCK_PREFIX.$criteriaKey, true, self::TTL_SECONDS);
    }

    public static function markMedicamentSyncCompleted(string $criteriaKey): void
    {
        Cache::forget(self::MEDICAMENT_SYNC_LOCK_PREFIX.$criteriaKey);
        Cache::forget(self::MEDICAMENT_STALE);
        Cache::put(self::MEDICAMENT_LAST_SYNC_PREFIX.$criteriaKey, now(), self::TTL_SECONDS);
    }

    public static function invalidateMedicamentSync(): void
    {
        Cache::put(self::MEDICAMENT_STALE, true, 86400);
    }
}
