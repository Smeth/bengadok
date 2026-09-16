<?php

namespace Tests\Feature\Pharmacies;

use App\Models\Heur;
use App\Models\TypePharmacie;
use App\Services\CommandeReferentielsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class PharmaciePartenaireTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $heur = Heur::query()->create(['ouverture' => '08:00', 'fermeture' => '18:00']);
        TypePharmacie::query()->create(['designation' => 'Standard', 'heurs_id' => $heur->id]);
    }

    public function test_index_filters_partner_pharmacies(): void
    {
        $admin = $this->userWithRole('admin');
        $partenaire = $this->createPharmacie(null, [
            'designation' => 'Pharmacie Partenaire',
            'est_partenaire' => true,
        ]);
        $nonPartenaire = $this->createPharmacie(null, [
            'designation' => 'Pharmacie Import',
            'est_partenaire' => false,
        ]);

        $this->actingAs($admin)
            ->get('/pharmacies?partenaire=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.partenaire', '1')
                ->has('pharmacies.data', 1)
                ->where('pharmacies.data.0.id', $partenaire->id)
            );

        $this->actingAs($admin)
            ->get('/pharmacies?partenaire=0')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.partenaire', '0')
                ->has('pharmacies.data', 1)
                ->where('pharmacies.data.0.id', $nonPartenaire->id)
            );
    }

    public function test_admin_can_promote_non_partner_pharmacy(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie(null, [
            'designation' => 'Pharmacie Import',
            'est_partenaire' => false,
        ]);
        CommandeReferentielsService::invalidateCache();

        $this->actingAs($admin)
            ->patch(route('pharmacies.promote-partenaire', $pharmacie))
            ->assertRedirect()
            ->assertSessionHas('status');

        $fresh = $pharmacie->fresh();
        $this->assertTrue($fresh->est_partenaire);
        $this->assertFalse($fresh->credits_actif);

        $response = $this->actingAs($admin)
            ->getJson('/commandes/referentiels')
            ->assertOk();

        $ids = collect($response->json('pharmacies'))->pluck('id')->all();
        $this->assertContains($pharmacie->id, $ids);
    }

    public function test_admin_can_create_pharmacy_user_with_credentials_flash(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie(null, ['est_partenaire' => true]);

        $this->actingAs($admin)
            ->post("/pharmacies/{$pharmacie->id}/users", [
                'name' => 'Jean Dupont',
                'phone' => '+241060000001',
                'role' => 'gerant',
                'password' => 'Secret123!',
            ])
            ->assertRedirect()
            ->assertSessionHas('createdUsername')
            ->assertSessionHas('createdPassword', 'Secret123!');

        $this->assertDatabaseHas('users', [
            'pharmacie_id' => $pharmacie->id,
            'phone' => '+241060000001',
        ]);
    }

    public function test_admin_can_toggle_est_partenaire_via_update(): void
    {
        $admin = $this->userWithRole('admin');
        $type = TypePharmacie::query()->firstOrFail();
        $pharmacie = $this->createPharmacie(null, [
            'designation' => 'Pharmacie Partenaire',
            'est_partenaire' => true,
            'proprio_nom' => 'Proprio',
            'type_pharmacie_id' => $type->id,
            'heurs_id' => $type->heurs_id,
        ]);

        $this->actingAs($admin)
            ->patch(route('pharmacies.update', $pharmacie), [
                'designation' => $pharmacie->designation,
                'adresse' => $pharmacie->adresse,
                'telephone' => $pharmacie->telephone,
                'type_pharmacie_id' => $type->id,
                'heure_ouverture' => '08:00',
                'heure_fermeture' => '19:00',
                'proprio_nom' => $pharmacie->proprio_nom,
                'est_partenaire' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($pharmacie->fresh()->est_partenaire);
    }
}
