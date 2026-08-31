<?php

namespace Tests\Feature\Backoffice;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class BackofficeNotificationsTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_backoffice_shared_notifications_and_stats_include_en_attente_and_nouvelles(): void
    {
        $this->seedRoles();

        $pharmacie = $this->createPharmacie();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $client = $this->createClient();

        $this->createCommande($client, $pharmacie, [
            'status' => 'en_attente',
            'status_pharmacie' => 'attente_confirmation',
        ]);

        $this->createCommande($client, $pharmacie, [
            'status' => 'nouvelle',
            'status_pharmacie' => 'nouvelle',
        ]);

        $this->actingAs($admin)
            ->get('/commandes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.count', 2)
                ->where('notifications.count_en_attente', 1)
                ->where('notifications.count_nouvelles', 1)
                ->where('backofficeStats.en_attente', 1)
                ->where('backofficeStats.nouvelles', 1)
                ->has('notifications.items', 2)
                ->where('notifications.items.0.alert_kind', 'en_attente')
                ->where('notifications.items.1.alert_kind', 'nouvelle')
            );
    }
}
