<?php

namespace Tests\Feature\Pharmacy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class PharmacyNotificationsTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_pharmacy_shared_notifications_and_stats_include_nouvelles_and_a_preparer(): void
    {
        $this->seedRoles();

        $pharmacie = $this->createPharmacie();
        $gerant = User::factory()->create(['pharmacie_id' => $pharmacie->id]);
        $gerant->assignRole('gerant');

        $client = $this->createClient();

        $this->createCommande($client, $pharmacie, [
            'status' => 'nouvelle',
            'status_pharmacie' => 'nouvelle',
        ]);

        $this->createCommande($client, $pharmacie, [
            'status' => 'validee',
            'status_pharmacie' => 'valide_a_preparer',
        ]);

        $this->actingAs($gerant)
            ->get('/dok-pharma/commandes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.count', 2)
                ->where('notifications.count_nouvelles', 1)
                ->where('notifications.count_a_preparer', 1)
                ->where('pharmacyStats.nouvelles', 1)
                ->where('pharmacyStats.a_preparer', 1)
                ->has('notifications.items', 2)
                ->where('notifications.items.0.alert_kind', 'nouvelle')
                ->where('notifications.items.1.alert_kind', 'a_preparer')
            );
    }
}
