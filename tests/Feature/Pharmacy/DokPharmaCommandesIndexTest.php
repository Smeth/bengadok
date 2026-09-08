<?php

namespace Tests\Feature\Pharmacy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class DokPharmaCommandesIndexTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_nouvelles_tab_returns_commande_on_first_page(): void
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

        $this->actingAs($gerant)
            ->get('/dok-pharma/commandes?onglet=nouvelles')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DokPharma/Index')
                ->where('stats.nouvelles', 1)
                ->where('commandes.total', 1)
                ->has('commandes.data', 1)
            );
    }

    public function test_nouvelles_tab_clamps_invalid_page_to_show_results(): void
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

        $this->actingAs($gerant)
            ->get('/dok-pharma/commandes?onglet=nouvelles&page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.nouvelles', 1)
                ->where('commandes.total', 1)
                ->has('commandes.data', 1)
                ->where('commandes.current_page', 1)
            );
    }
}
