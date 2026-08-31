<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class OrderAlertSettingsTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_gerant_can_open_order_alert_settings_page(): void
    {
        $this->seedRoles();

        $pharmacie = $this->createPharmacie();
        $gerant = User::factory()->create(['pharmacie_id' => $pharmacie->id]);
        $gerant->assignRole('gerant');

        $this->withoutVite()
            ->actingAs($gerant)
            ->get('/settings/alertes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('settings/Alertes'));
    }

    public function test_admin_can_open_order_alert_settings_page(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->withoutVite()
            ->actingAs($admin)
            ->get('/settings/alertes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('settings/Alertes'));
    }
}
