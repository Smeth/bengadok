<?php

namespace Tests\Feature;

use App\Services\PharmacieCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class PharmacieCreditTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_recharge_increments_pharmacy_balance(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie(null, [
            'credits_solde' => 5,
            'est_partenaire' => true,
        ]);

        $service = app(PharmacieCreditService::class);
        $service->recharger($pharmacie, 10, 'especes', 'Test recharge', $admin);

        $this->assertSame(15, (int) $pharmacie->fresh()->credits_solde);
    }

    public function test_recharge_below_minimum_is_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie(null, ['est_partenaire' => true]);

        $this->expectException(\InvalidArgumentException::class);

        app(PharmacieCreditService::class)->recharger($pharmacie, 1, 'especes', null, $admin);
    }

    public function test_disabled_credits_show_desactive_status_instead_of_actif(): void
    {
        $pharmacie = $this->createPharmacie(null, [
            'est_partenaire' => true,
            'credits_actif' => false,
            'credits_solde' => 50,
        ]);

        $payload = app(PharmacieCreditService::class)->buildGestionPayload($pharmacie);

        $this->assertSame('desactive', $payload['resume']['statut']);
        $this->assertSame('Désactivé', $payload['resume']['statut_label']);
        $this->assertFalse($payload['resume']['credits_actif']);

        $overview = app(PharmacieCreditService::class)->buildIndexCreditsOverview();
        $row = collect($overview)->firstWhere('id', $pharmacie->id);

        $this->assertNotNull($row);
        $this->assertSame('desactive', $row['statut']);
        $this->assertSame('Désactivé', $row['statut_label']);
    }
}
