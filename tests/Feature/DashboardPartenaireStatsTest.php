<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Services\AdminParapharmaDashboardService;
use App\Services\DashboardStatsService;
use App\Services\PharmacieCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class DashboardPartenaireStatsTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_operations_dashboard_excludes_non_partner_pharmacies(): void
    {
        $client = $this->createClient();
        $partenaire = $this->createPharmacie(null, [
            'designation' => 'Partenaire A',
            'est_partenaire' => true,
        ]);
        $nonPartenaire = $this->createPharmacie(null, [
            'designation' => 'Historique B',
            'est_partenaire' => false,
        ]);

        $today = now()->toDateString();

        $this->createCommande($client, $partenaire, [
            'date' => $today,
            'status' => 'validee',
            'status_pharmacie' => Commande::STATUT_PHARMACIE_CA_COMPTABILISE,
            'prix_total' => 10_000,
            'prix_medicaments' => 10_000,
        ]);
        $this->createCommande($client, $nonPartenaire, [
            'date' => $today,
            'status' => 'validee',
            'status_pharmacie' => Commande::STATUT_PHARMACIE_CA_COMPTABILISE,
            'prix_total' => 20_000,
            'prix_medicaments' => 20_000,
        ]);

        $stats = app(DashboardStatsService::class)->build(null, 'month');

        $this->assertSame(1, $stats['kpis']['nbPharmacies']);
        $this->assertSame(1, $stats['kpis']['nbPharmaciesActives']);
        $this->assertSame(1, $stats['kpis']['nbCommandes']);
        $this->assertSame(10_000.0, $stats['kpis']['revenuTotal']);
    }

    public function test_parapharma_dashboard_credits_overview_lists_partners_only(): void
    {
        $this->createPharmacie(null, [
            'designation' => 'Partenaire',
            'est_partenaire' => true,
        ]);
        $this->createPharmacie(null, [
            'designation' => 'Non partenaire',
            'est_partenaire' => false,
            'credits_solde' => 99,
        ]);

        $overview = app(PharmacieCreditService::class)->buildIndexCreditsOverview();

        $this->assertCount(1, $overview);
        $this->assertSame('Partenaire', $overview[0]['designation']);
    }

    public function test_parapharma_commissions_list_partners_only_and_respect_credits_actif(): void
    {
        $this->createPharmacie(null, [
            'designation' => 'Non partenaire',
            'est_partenaire' => false,
        ]);
        $partenaireInactif = $this->createPharmacie(null, [
            'designation' => 'Sans parapharma',
            'est_partenaire' => true,
            'credits_actif' => false,
        ]);
        $this->createPharmacie(null, [
            'designation' => 'Partenaire actif',
            'est_partenaire' => true,
            'credits_actif' => true,
        ]);

        $dashboard = app(AdminParapharmaDashboardService::class)->build();
        $commissions = collect($dashboard['commissions_par_pharmacie']);

        $this->assertCount(2, $commissions);
        $this->assertNull($commissions->firstWhere('pharmacie', 'Non partenaire'));

        $inactiveRow = $commissions->firstWhere('pharmacie_id', $partenaireInactif->id);
        $this->assertNotNull($inactiveRow);
        $this->assertFalse($inactiveRow['credits_actif']);
        $this->assertSame(0, $inactiveRow['montant_commission']);
    }

    public function test_dok_pharma_dashboard_returns_inactif_payload_for_non_partner(): void
    {
        $this->ensureRolesSeeded();

        $pharmacie = $this->createPharmacie(null, [
            'est_partenaire' => false,
            'credits_actif' => false,
        ]);

        $gerant = $this->userWithRole('gerant');
        $gerant->update(['pharmacie_id' => $pharmacie->id]);

        $payload = app(AdminParapharmaDashboardService::class)->build(
            null,
            $pharmacie->id,
        );

        $this->assertFalse($payload['parapharma_actif']);
        $this->assertSame('non_partenaire', $payload['parapharma_inactif_raison']);
        $this->assertSame([], $payload['ventes']);
        $this->assertSame(0, $payload['kpis']['nb_commandes']);
    }
}
