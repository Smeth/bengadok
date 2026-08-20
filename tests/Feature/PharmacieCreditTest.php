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
        $pharmacie = $this->createPharmacie(null, ['credits_solde' => 5]);

        $service = app(PharmacieCreditService::class);
        $service->recharger($pharmacie, 10, 'especes', 'Test recharge', $admin);

        $this->assertSame(15, (int) $pharmacie->fresh()->credits_solde);
    }

    public function test_recharge_below_minimum_is_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();

        $this->expectException(\InvalidArgumentException::class);

        app(PharmacieCreditService::class)->recharger($pharmacie, 1, 'especes', null, $admin);
    }
}
