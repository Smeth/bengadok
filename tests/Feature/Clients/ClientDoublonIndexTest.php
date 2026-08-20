<?php

namespace Tests\Feature\Clients;

use App\Jobs\SyncClientDoublonsJob;
use App\Services\ClientDoublonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class ClientDoublonIndexTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    public function test_doublons_index_queues_sync_instead_of_blocking_rescan(): void
    {
        Queue::fake();
        Cache::flush();

        $admin = $this->userWithRole('admin');

        $mock = Mockery::mock(ClientDoublonService::class);
        $mock->shouldNotReceive('detecterEtCreerGroupes');
        $this->app->instance(ClientDoublonService::class, $mock);

        $this->actingAs($admin)
            ->get('/clients/doublons')
            ->assertOk();

        Queue::assertPushed(SyncClientDoublonsJob::class);
    }
}
