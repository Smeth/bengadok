<?php

namespace Tests\Feature\Commandes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class CommandeReferentielsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    public function test_admin_can_load_commande_referentiels(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->getJson('/commandes/referentiels')
            ->assertOk()
            ->assertJsonStructure([
                'pharmacies',
                'zones',
                'montantsLivraison',
                'modesPaiement',
                'livreurs',
                'arrondissements',
                'parapharma_produit_types',
            ]);
    }

    public function test_vendeur_cannot_load_commande_referentiels(): void
    {
        $vendeur = $this->userWithRole('vendeur');

        $this->actingAs($vendeur)
            ->getJson('/commandes/referentiels')
            ->assertRedirect('/dok-pharma/commandes');
    }

    public function test_commandes_index_does_not_embed_heavy_referentiels(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/commandes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('pharmacies')
                ->missing('zones')
                ->where('canManageCommandes', true)
            );
    }
}
