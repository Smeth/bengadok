<?php

namespace Tests\Feature\Commandes;

use App\Models\Client;
use App\Models\Heur;
use App\Models\Pharmacie;
use App\Models\TypePharmacie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class CommandeQuickCreatePharmacieTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createZone('Poto-Poto');
        $heur = Heur::query()->create(['ouverture' => '08:00', 'fermeture' => '18:00']);
        TypePharmacie::query()->create(['designation' => 'Standard', 'heurs_id' => $heur->id]);
    }

    public function test_admin_can_quick_create_pharmacie_from_commande_flow(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('commandes.pharmacies.quick-create'), [
                'designation' => 'Auréole',
                'arrondissement' => Client::ARRONDISSEMENTS[0],
                'telephone' => '0611223344',
                'adresse' => 'Centre-ville',
            ])
            ->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('pharmacie.designation', 'Pharmacie Auréole');

        $this->assertDatabaseHas('pharmacies', [
            'designation' => 'Pharmacie Auréole',
            'telephone' => '0611223344',
            'adresse' => 'Centre-ville',
            'est_partenaire' => false,
        ]);
    }

    public function test_quick_create_returns_existing_pharmacy_when_name_matches(): void
    {
        $admin = $this->userWithRole('admin');
        $existing = $this->createPharmacie(null, ['designation' => 'Pharmacie Clairon']);

        $this->actingAs($admin)
            ->postJson(route('commandes.pharmacies.quick-create'), [
                'designation' => 'Clairon',
                'arrondissement' => Client::ARRONDISSEMENTS[0],
            ])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('pharmacie.id', $existing->id);

        $this->assertSame(1, Pharmacie::query()->where('designation', 'like', '%Clairon%')->count());
    }

    public function test_agent_cannot_quick_create_pharmacie(): void
    {
        $agent = $this->userWithRole('agent_call_center');

        $response = $this->actingAs($agent)
            ->postJson(route('commandes.pharmacies.quick-create'), [
                'designation' => 'Nouvelle',
                'arrondissement' => Client::ARRONDISSEMENTS[0],
            ]);

        $this->assertContains($response->getStatusCode(), [403, 302]);
        $this->assertDatabaseMissing('pharmacies', [
            'designation' => 'Pharmacie Nouvelle',
        ]);
    }
}
