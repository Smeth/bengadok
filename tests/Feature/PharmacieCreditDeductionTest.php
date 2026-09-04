<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Commande;
use App\Models\PharmacieCreditOperation;
use App\Models\Produit;
use App\Models\User;
use App\Services\PharmacieCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class PharmacieCreditDeductionTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_credit_deducted_on_pharmacy_retrait_when_medicaments_above_threshold(): void
    {
        AppSetting::ensureRowExists()->update([
            'parapharma_credit_deduction_auto' => true,
            'parapharma_credit_seuil_medicament_xaf' => 3000,
        ]);

        $pharmacie = $this->createPharmacie(null, [
            'credits_solde' => 5,
            'credits_actif' => true,
        ]);
        $client = $this->createClient();
        $commande = $this->createCommande($client, $pharmacie, [
            'status' => 'validee',
            'status_pharmacie' => 'valide_a_preparer',
            'prix_medicaments' => 6000,
            'prix_parapharma' => 2000,
            'prix_total' => 8000,
        ]);

        $med = Produit::query()->create([
            'designation' => 'Med test',
            'dosage' => '500mg',
            'forme' => 'Comprimé',
        ]);
        $para = Produit::query()->create([
            'designation' => 'Para test',
            'dosage' => null,
            'forme' => null,
            'type' => 'Parapharmacie',
        ]);

        $commande->produits()->attach($med->id, [
            'quantite' => 2,
            'quantite_confirmee' => 2,
            'prix_unitaire' => 3000,
            'status' => 'disponible',
            'type' => null,
        ]);
        $commande->produits()->attach($para->id, [
            'quantite' => 1,
            'quantite_confirmee' => 1,
            'prix_unitaire' => 2000,
            'status' => 'disponible',
            'type' => 'Parapharmacie',
        ]);

        $this->seedRoles();
        $gerant = User::factory()->create(['pharmacie_id' => $pharmacie->id]);
        $gerant->assignRole('gerant');

        $this->actingAs($gerant)
            ->post("/dok-pharma/{$commande->id}/valider-retrait")
            ->assertRedirect();

        $this->assertSame(4, (int) $pharmacie->fresh()->credits_solde);
        $this->assertTrue(
            PharmacieCreditOperation::query()
                ->where('commande_id', $commande->id)
                ->where('type', PharmacieCreditOperation::TYPE_DEDUCTION)
                ->exists()
        );

        $commande->refresh();
        $this->assertSame(6000.0, (float) $commande->prix_medicaments);
        $this->assertSame(2000.0, (float) $commande->prix_parapharma);
    }

    public function test_no_credit_when_medicaments_below_threshold_despite_parapharma(): void
    {
        AppSetting::ensureRowExists()->update([
            'parapharma_credit_deduction_auto' => true,
            'parapharma_credit_seuil_medicament_xaf' => 5000,
        ]);

        $pharmacie = $this->createPharmacie(null, [
            'credits_solde' => 5,
            'credits_actif' => true,
        ]);
        $client = $this->createClient();
        $commande = $this->createCommande($client, $pharmacie, [
            'status' => 'validee',
            'status_pharmacie' => 'valide_a_preparer',
        ]);

        $med = Produit::query()->create(['designation' => 'Med faible', 'dosage' => '100mg', 'forme' => 'Sirop']);
        $para = Produit::query()->create([
            'designation' => 'Para cher',
            'type' => 'Parapharmacie',
        ]);

        $commande->produits()->attach($med->id, [
            'quantite' => 1,
            'quantite_confirmee' => 1,
            'prix_unitaire' => 2000,
            'status' => 'disponible',
        ]);
        $commande->produits()->attach($para->id, [
            'quantite' => 1,
            'quantite_confirmee' => 1,
            'prix_unitaire' => 8000,
            'status' => 'disponible',
            'type' => 'Parapharmacie',
        ]);

        $commande->update(['status_pharmacie' => Commande::STATUT_PHARMACIE_CA_COMPTABILISE]);
        $op = app(PharmacieCreditService::class)->deduirePourCommande($commande->fresh());

        $this->assertNull($op);
        $this->assertSame(5, (int) $pharmacie->fresh()->credits_solde);
    }

    public function test_credit_deducted_when_pivot_still_en_attente_at_retrait(): void
    {
        AppSetting::ensureRowExists()->update([
            'parapharma_credit_deduction_auto' => true,
            'parapharma_credit_seuil_medicament_xaf' => 3000,
        ]);

        $pharmacie = $this->createPharmacie(null, [
            'credits_solde' => 3,
            'credits_actif' => true,
        ]);
        $client = $this->createClient();
        $commande = $this->createCommande($client, $pharmacie, [
            'status' => 'validee',
            'status_pharmacie' => Commande::STATUT_PHARMACIE_CA_COMPTABILISE,
            'prix_medicaments' => 6000,
            'prix_parapharma' => 0,
            'prix_total' => 6000,
        ]);

        $med = Produit::query()->create([
            'designation' => 'Med en attente',
            'dosage' => '250mg',
            'forme' => 'Gélule',
        ]);

        $commande->produits()->attach($med->id, [
            'quantite' => 2,
            'prix_unitaire' => 3000,
            'status' => 'en_attente',
        ]);

        $op = app(PharmacieCreditService::class)->deduirePourCommande($commande->fresh());

        $this->assertNotNull($op);
        $this->assertSame(2, (int) $pharmacie->fresh()->credits_solde);
        $commande->refresh();
        $this->assertSame(6000.0, (float) $commande->prix_medicaments);
    }
}
