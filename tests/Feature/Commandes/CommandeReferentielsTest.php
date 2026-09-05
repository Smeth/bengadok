<?php

namespace Tests\Feature\Commandes;

use App\Services\CommandeReferentielsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class CommandeReferentielsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        CommandeReferentielsService::invalidateCache();
    }

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

    public function test_referentiels_are_cached_after_first_load(): void
    {
        Cache::flush();
        $this->assertFalse(Cache::has(CommandeReferentielsService::CACHE_KEY));

        app(CommandeReferentielsService::class)->all();

        $this->assertTrue(Cache::has(CommandeReferentielsService::CACHE_KEY));
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

    public function test_commandes_index_list_uses_medicaments_resume(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/commandes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('commandes')
                ->missing('commandes.data.0.produits')
            );
    }
}
