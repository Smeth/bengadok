<?php

namespace App\Jobs;

use App\Services\ClientDoublonService;
use App\Services\DoublonSyncCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncClientDoublonsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ClientDoublonService $doublonService): void
    {
        try {
            $doublonService->detecterEtCreerGroupes();
        } finally {
            DoublonSyncCache::markClientSyncCompleted();
        }
    }
}
