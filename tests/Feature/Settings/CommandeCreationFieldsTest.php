<?php

namespace Tests\Feature\Settings;

use App\Models\AppSetting;
use App\Models\Client;
use App\Support\CommandeCreationFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class CommandeCreationFieldsTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    /**
     * @return array<string, mixed>
     */
    private function validCommandePayload(int $pharmacieId): array
    {
        return [
            'pharmacie_id' => $pharmacieId,
            'client_prenom' => 'Jean',
            'client_tel' => '0612345678',
            'client_adresse' => '20 rue test',
            'client_arrondissement' => Client::ARRONDISSEMENTS[0],
            'produits' => [
                [
                    'designation' => 'Paracétamol',
                    'quantite' => 1,
                    'prix_unitaire' => 1000,
                ],
            ],
        ];
    }

    public function test_admin_can_update_commande_creation_field_settings(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->patch('/settings/parametres/commande-creation-champs', [
                'champs' => [
                    'client_nom' => true,
                    'client_prenom' => false,
                    'commentaire' => true,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $config = AppSetting::commandeCreationFieldsConfig();
        $this->assertTrue($config['client_nom']);
        $this->assertFalse($config['client_prenom']);
        $this->assertTrue($config['commentaire']);
    }

    public function test_commande_store_requires_fields_marked_obligatory(): void
    {
        AppSetting::ensureRowExists()->update([
            'commande_creation_champs' => [
                'client_prenom' => true,
                'client_tel' => true,
                'client_adresse' => true,
                'client_arrondissement' => true,
            ],
        ]);

        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();

        $payload = $this->validCommandePayload($pharmacie->id);
        unset($payload['client_prenom']);

        $this->actingAs($admin)
            ->post('/commandes', $payload)
            ->assertSessionHasErrors('client_prenom');
    }

    public function test_commande_store_accepts_optional_field_when_not_required(): void
    {
        AppSetting::ensureRowExists()->update([
            'commande_creation_champs' => [
                'client_prenom' => false,
                'client_tel' => true,
                'client_adresse' => true,
                'client_arrondissement' => true,
            ],
        ]);

        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();

        $payload = $this->validCommandePayload($pharmacie->id);
        unset($payload['client_prenom']);

        $this->actingAs($admin)
            ->post('/commandes', $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_agent_route_does_not_require_admin_only_beneficiaire(): void
    {
        AppSetting::ensureRowExists()->update([
            'commande_creation_champs' => [
                'beneficiaire' => true,
                'client_prenom' => false,
                'client_tel' => true,
                'client_adresse' => true,
                'client_arrondissement' => false,
            ],
        ]);

        $agent = $this->userWithRole('agent_call_center');
        $pharmacie = $this->createPharmacie();

        $payload = [
            'pharmacie_id' => $pharmacie->id,
            'client_nouveau' => json_encode([
                'nom' => 'Test',
                'prenom' => 'Agent',
                'tel' => '0699999999',
                'adresse' => 'Adresse agent',
            ]),
            'produits' => json_encode([
                [
                    'designation' => 'Ibuprofène',
                    'quantite' => 1,
                    'prix_unitaire' => 500,
                ],
            ]),
        ];

        $this->actingAs($agent)
            ->post('/agent/commande', $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_definitions_for_frontend_include_contexts(): void
    {
        $definitions = CommandeCreationFields::definitionsForFrontend();

        $beneficiaire = collect($definitions)->firstWhere('key', 'beneficiaire');
        $this->assertNotNull($beneficiaire);
        $this->assertSame(['admin'], $beneficiaire['contexts']);
    }
}
