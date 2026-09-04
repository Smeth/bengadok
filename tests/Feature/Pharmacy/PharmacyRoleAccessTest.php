<?php

namespace Tests\Feature\Pharmacy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class PharmacyRoleAccessTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_vendeur_is_redirected_from_dashboard_to_commandes(): void
    {
        $pharmacie = $this->createPharmacie();
        $vendeur = $this->userWithRole('vendeur', ['pharmacie_id' => $pharmacie->id]);

        $this->actingAs($vendeur)
            ->get('/dok-pharma')
            ->assertRedirect('/dok-pharma/commandes');
    }

    public function test_vendeur_cannot_access_vendeurs_management(): void
    {
        $pharmacie = $this->createPharmacie();
        $vendeur = $this->userWithRole('vendeur', ['pharmacie_id' => $pharmacie->id]);

        $this->actingAs($vendeur)
            ->get('/pharmacie/vendeurs')
            ->assertRedirect('/dok-pharma/commandes');
    }

    public function test_vendeur_is_redirected_from_livrees_historique(): void
    {
        $pharmacie = $this->createPharmacie();
        $vendeur = $this->userWithRole('vendeur', ['pharmacie_id' => $pharmacie->id]);

        $this->actingAs($vendeur)
            ->get('/dok-pharma/commandes?onglet=livrees')
            ->assertRedirect('/dok-pharma/commandes?onglet=nouvelles');
    }

    public function test_vendeur_cannot_recharge_credits_or_mark_commission_paid(): void
    {
        $pharmacie = $this->createPharmacie();
        $vendeur = $this->userWithRole('vendeur', ['pharmacie_id' => $pharmacie->id]);

        $this->actingAs($vendeur)
            ->post('/dok-pharma/credits/recharge', [
                'nombre_credits' => 10,
                'mode_paiement' => 'Espèces',
            ])
            ->assertRedirect('/dok-pharma/commandes');

        $this->actingAs($vendeur)
            ->post('/dok-pharma/commission/payee', ['mois' => now()->format('Y-m')])
            ->assertRedirect('/dok-pharma/commandes');
    }

    public function test_gerant_is_redirected_from_legacy_dashboard(): void
    {
        $pharmacie = $this->createPharmacie();
        $gerant = $this->userWithRole('gerant', ['pharmacie_id' => $pharmacie->id]);

        $this->actingAs($gerant)
            ->get('/dashboard')
            ->assertRedirect('/dok-pharma');
    }

    public function test_gerant_can_access_dashboard_vendeurs_and_historique(): void
    {
        $pharmacie = $this->createPharmacie();
        $gerant = $this->userWithRole('gerant', ['pharmacie_id' => $pharmacie->id]);

        $this->actingAs($gerant)
            ->get('/dok-pharma')
            ->assertOk();

        $this->actingAs($gerant)
            ->get('/pharmacie/vendeurs')
            ->assertOk();

        $this->actingAs($gerant)
            ->get('/dok-pharma/commandes?onglet=livrees')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DokPharma/Index')
                ->where('canViewHistorique', true)
                ->where('onglet', 'livrees')
            );
    }

    public function test_vendeur_commandes_page_hides_historique_flag(): void
    {
        $pharmacie = $this->createPharmacie();
        $vendeur = $this->userWithRole('vendeur', ['pharmacie_id' => $pharmacie->id]);

        $this->actingAs($vendeur)
            ->get('/dok-pharma/commandes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DokPharma/Index')
                ->where('canViewHistorique', false)
            );
    }
}
