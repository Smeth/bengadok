<?php

namespace Tests\Feature\Clients;

use App\Actions\PromoteClientsFromSuccessfulOrdersAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class ProspectPromotionTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_prospect_with_only_validated_order_is_not_eligible(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();
        $prospect = $this->createClient(['promu_client_le' => null]);
        $this->createCommande($prospect, $pharmacie, ['status' => 'validee']);

        $this->actingAs($admin)
            ->get('/clients/prospects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('prospects.data.0.statut', 'en_cours')
                ->where('stats.eligibles_promotion', 0)
            );
    }

    public function test_prospect_with_delivered_order_is_eligible_and_can_be_promoted_manually(): void
    {
        $agent = $this->userWithRole('agent_call_center');
        $pharmacie = $this->createPharmacie();
        $prospect = $this->createClient(['promu_client_le' => null]);
        $this->createCommande($prospect, $pharmacie, ['status' => 'retiree']);

        $this->actingAs($agent)
            ->get('/clients/prospects')
            ->assertInertia(fn ($page) => $page
                ->where('prospects.data.0.statut', 'eligible_promotion')
                ->where('stats.eligibles_promotion', 1)
            );

        $this->actingAs($agent)
            ->patch(route('clients.promouvoir', $prospect))
            ->assertRedirect();

        $prospect->refresh();
        $this->assertNotNull($prospect->promu_client_le);
    }

    public function test_manual_promotion_requires_delivered_order(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();
        $prospect = $this->createClient(['promu_client_le' => null]);
        $this->createCommande($prospect, $pharmacie, ['status' => 'en_attente']);

        $this->actingAs($admin)
            ->from('/clients/prospects')
            ->patch(route('clients.promouvoir', $prospect))
            ->assertRedirect('/clients/prospects')
            ->assertSessionHas('error');

        $this->assertNull($prospect->fresh()->promu_client_le);
    }

    public function test_auto_promotion_on_delivered_status_only(): void
    {
        $this->seedRoles();

        $pharmacie = $this->createPharmacie();
        $prospect = $this->createClient(['promu_client_le' => null]);
        $commande = $this->createCommande($prospect, $pharmacie, ['status' => 'validee']);

        PromoteClientsFromSuccessfulOrdersAction::afterAdmin($commande, 'validee');
        $this->assertNull($prospect->fresh()->promu_client_le);

        PromoteClientsFromSuccessfulOrdersAction::afterAdmin($commande, 'retiree');
        $this->assertNotNull($prospect->fresh()->promu_client_le);
    }
}
