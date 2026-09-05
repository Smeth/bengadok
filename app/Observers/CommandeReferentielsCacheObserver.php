<?php

namespace App\Observers;

use App\Services\CommandeReferentielsService;

/**
 * Invalide le cache des référentiels commande quand une entité liée change.
 */
class CommandeReferentielsCacheObserver
{
    public function saved(object $model): void
    {
        CommandeReferentielsService::invalidateCache();
    }

    public function deleted(object $model): void
    {
        CommandeReferentielsService::invalidateCache();
    }
}
