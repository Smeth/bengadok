<?php

namespace Tests\Feature\Commandes;

use App\Models\Client;
use App\Models\Heur;
use App\Models\Produit;
use App\Models\TypePharmacie;
use App\Services\PharmacieProximiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class PharmaciePartenaireCommandeFlowTest extends TestCase
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

    public function test_commandes_store_rejects_non_partner_pharmacy(): void
    {
        $admin = $this->userWithRole('admin');
        $zone = $this->createZone('Poto-Poto');
        $nonPartenaire = $this->createPharmacie($zone, [
            'designation' => 'Pharmacie Import',
            'est_partenaire' => false,
        ]);

        $this->actingAs($admin)
            ->post('/commandes', [
                'pharmacie_id' => $nonPartenaire->id,
                'client_prenom' => 'Jean',
                'client_tel' => '0611223344',
                'client_adresse' => 'Centre',
                'client_arrondissement' => Client::ARRONDISSEMENTS[0],
                'produits' => [
                    [
                        'designation' => 'Doliprane',
                        'quantite' => 1,
                        'prix_unitaire' => 1500,
                    ],
                ],
            ])
            ->assertSessionHasErrors('pharmacie_id');
    }

    public function test_gestion_hub_allows_non_partner_pharmacy(): void
    {
        $admin = $this->userWithRole('admin');
        $zone = $this->createZone('Moungali');
        $nonPartenaire = $this->createPharmacie($zone, [
            'designation' => 'Pharmacie Import',
            'est_partenaire' => false,
        ]);

        $this->actingAs($admin)
            ->post('/commandes', [
                'pharmacie_id' => $nonPartenaire->id,
                'client_prenom' => 'Jean',
                'client_tel' => '0611334455',
                'client_adresse' => 'Centre',
                'client_arrondissement' => Client::ARRONDISSEMENTS[0],
                'produits' => [
                    [
                        'designation' => 'Doliprane',
                        'quantite' => 1,
                        'prix_unitaire' => 1500,
                    ],
                ],
                '_return_hub' => 'gestion',
                'initial_status' => 'nouvelle',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    public function test_proximite_service_returns_only_partner_pharmacies(): void
    {
        $zone = $this->createZone('Poto-Poto');
        $partenaire = $this->createPharmacie($zone, [
            'designation' => 'Pharmacie Partenaire Poto',
            'est_partenaire' => true,
        ]);
        $this->createPharmacie($zone, [
            'designation' => 'Pharmacie Import Poto',
            'est_partenaire' => false,
        ]);

        $results = app(PharmacieProximiteService::class)
            ->trouverPharmaciesProches('Poto-Poto centre-ville')
            ->pluck('id')
            ->all();

        $this->assertContains($partenaire->id, $results);
        $this->assertCount(1, $results);
    }

    public function test_agent_renvoi_partiel_rejects_non_partner_pharmacy(): void
    {
        $agent = $this->userWithRole('agent_call_center');
        $zone = $this->createZone('Poto-Poto');
        $partenaire = $this->createPharmacie($zone, [
            'designation' => 'Pharmacie Partenaire A',
            'est_partenaire' => true,
        ]);
        $nonPartenaire = $this->createPharmacie($zone, [
            'designation' => 'Pharmacie Import',
            'est_partenaire' => false,
        ]);
        $client = $this->createClient(['adresse' => 'Poto-Poto centre']);
        $commande = $this->createCommande($client, $partenaire, ['status' => 'en_attente']);
        $produit = Produit::query()->create([
            'designation' => 'Doliprane',
            'dosage' => '500mg',
            'forme' => 'Comprimé',
            'pu' => 1500,
        ]);
        $commande->produits()->attach($produit->id, [
            'quantite' => 2,
            'prix_unitaire' => 1500,
            'status' => 'en_attente',
        ]);

        $this->actingAs($agent)
            ->post(route('agent.renvoyer-pharmacie-partiel', $commande), [
                'pharmacie_id' => $nonPartenaire->id,
                'lignes' => [
                    [
                        'produit_id' => $produit->id,
                        'quantite' => 1,
                    ],
                ],
            ])
            ->assertSessionHasErrors('pharmacie_id');
    }

    public function test_agent_nouvelle_commande_lists_only_partner_pharmacies(): void
    {
        $agent = $this->userWithRole('agent_call_center');
        $zone = $this->createZone('Makélékélé');
        $partenaire = $this->createPharmacie($zone, [
            'designation' => 'Pharmacie Partenaire',
            'est_partenaire' => true,
        ]);
        $this->createPharmacie($zone, [
            'designation' => 'Pharmacie Import',
            'est_partenaire' => false,
        ]);

        $this->actingAs($agent)
            ->get(route('agent.nouvelle-commande'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pharmacies', 1)
                ->where('pharmacies.0.id', $partenaire->id)
            );
    }
}
