<?php

namespace App\Jobs;

use App\Services\DoublonSyncCache;
use App\Services\ProduitDoublonService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncMedicamentDoublonsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $criteresActifs
     */
    public function __construct(
        public array $criteresActifs,
        public string $criteriaKey,
    ) {}

    public function handle(ProduitDoublonService $doublonService): void
    {
        try {
            $doublonService->resyncPourCriteres($this->criteresActifs);
        } finally {
            DoublonSyncCache::markMedicamentSyncCompleted($this->criteriaKey);
        }
    }
}
