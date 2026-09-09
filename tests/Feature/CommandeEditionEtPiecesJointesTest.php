<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommandePieceJointe;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class CommandeEditionEtPiecesJointesTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_complementaires_can_be_updated_on_validated_commande(): void
    {
        $this->seedRoles();
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();
        $client = $this->createClient();
        $commande = $this->createCommande($client, $pharmacie, [
            'status' => 'validee',
            'commentaire' => null,
            'beneficiaire' => null,
        ]);

        $this->actingAs($admin)
            ->patch("/commandes/{$commande->id}/complementaires", [
                'commentaire' => 'Appeler avant livraison',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $commande->refresh();
        $this->assertSame('Appeler avant livraison', $commande->commentaire);
        $this->assertNull($commande->beneficiaire);
    }

    public function test_pharmacy_can_upload_and_list_piece_jointe(): void
    {
        Storage::fake('local');
        $this->seedRoles();

        $pharmacie = $this->createPharmacie();
        $client = $this->createClient();
        $commande = $this->createCommande($client, $pharmacie, [
            'status' => 'en_attente',
            'status_pharmacie' => 'attente_confirmation',
        ]);

        $gerant = User::factory()->create(['pharmacie_id' => $pharmacie->id]);
        $gerant->assignRole('gerant');

        $file = UploadedFile::fake()->image('colis.jpg', 400, 400);

        $this->actingAs($gerant)
            ->post("/dok-pharma/{$commande->id}/pieces-jointes", [
                'fichier' => $file,
                'label' => 'Photo colis',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(1, CommandePieceJointe::query()->where('commande_id', $commande->id)->count());

        $this->actingAs($gerant)
            ->get('/dok-pharma/commandes?onglet=en_attente')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DokPharma/Index')
                ->has('commandes.data.0.pieces_jointes', 1));
    }

    public function test_creation_sets_date_and_heure_automatically(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(15, 45));

        AppSetting::ensureRowExists()->update([
            'commande_creation_champs' => [
                'client_prenom' => true,
                'client_tel' => true,
                'client_adresse' => true,
                'client_arrondissement' => true,
            ],
        ]);

        $this->seedRoles();
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();

        $payload = [
            'pharmacie_id' => $pharmacie->id,
            'client_prenom' => 'Paul',
            'client_tel' => '0611223344',
            'client_adresse' => '12 rue test',
            'client_arrondissement' => Client::ARRONDISSEMENTS[0],
            'date' => '2020-01-01',
            'heurs' => '08:00',
            'produits' => [
                [
                    'designation' => 'Vitamine C',
                    'quantite' => 1,
                    'prix_unitaire' => 2000,
                ],
            ],
        ];

        $this->actingAs($admin)
            ->post('/commandes', $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $commande = Commande::query()->latest('id')->first();
        $this->assertNotNull($commande);
        $this->assertSame(now()->format('Y-m-d'), $commande->date->format('Y-m-d'));
        $this->assertSame('15:45', $commande->heurs);
        $this->assertNull($commande->montant_livraison_id);
    }

    public function test_update_commande_persists_client_sexe_and_beneficiaire(): void
    {
        $this->seedRoles();
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();
        $client = $this->createClient(['sexe' => null]);
        $commande = $this->createCommande($client, $pharmacie, [
            'status' => 'nouvelle',
            'beneficiaire' => null,
        ]);

        $this->actingAs($admin)
            ->patch("/commandes/{$commande->id}", [
                'client_id' => $client->id,
                'client_nom' => $client->nom,
                'client_prenom' => $client->prenom,
                'client_tel' => $client->tel,
                'client_adresse' => $client->adresse,
                'client_sexe' => 'F',
                'pharmacie_id' => $pharmacie->id,
                'beneficiaire' => 'Sa mère',
                'produits' => [
                    [
                        'designation' => 'Paracétamol',
                        'quantite' => 1,
                        'prix_unitaire' => 1500,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $client->refresh();
        $commande->refresh();

        $this->assertSame('F', $client->sexe);
        $this->assertSame('Sa mère', $commande->beneficiaire);
        $this->assertSame(1500.0, (float) $commande->prix_medicaments);
        $this->assertSame(1500.0, (float) $commande->prix_total);
    }

    public function test_update_commande_preserves_montants_when_lines_still_en_attente(): void
    {
        $this->seedRoles();
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();
        $client = $this->createClient();
        $commande = $this->createCommande($client, $pharmacie, [
            'status' => 'nouvelle',
            'prix_medicaments' => 6000,
            'prix_parapharma' => 2000,
            'prix_total' => 8000,
        ]);

        $med = Produit::query()->create([
            'designation' => 'Stilnox',
            'dosage' => '10mg',
            'forme' => 'Comprimé',
        ]);
        $para = Produit::query()->create([
            'designation' => 'Lait Cerave',
            'type' => 'Parapharmacie',
        ]);

        $commande->produits()->attach($med->id, [
            'quantite' => 1,
            'prix_unitaire' => 6000,
            'status' => 'en_attente',
        ]);
        $commande->produits()->attach($para->id, [
            'quantite' => 1,
            'prix_unitaire' => 2000,
            'status' => 'en_attente',
            'type' => 'Parapharmacie',
        ]);

        $this->actingAs($admin)
            ->patch("/commandes/{$commande->id}", [
                'client_id' => $client->id,
                'client_nom' => $client->nom,
                'client_prenom' => $client->prenom,
                'client_tel' => $client->tel,
                'client_adresse' => $client->adresse,
                'pharmacie_id' => $pharmacie->id,
                'produits' => [
                    [
                        'id' => $med->id,
                        'designation' => 'Stilnox',
                        'dosage' => '10mg',
                        'forme' => 'Comprimé',
                        'quantite' => 1,
                        'prix_unitaire' => 6000,
                    ],
                    [
                        'id' => $para->id,
                        'designation' => 'Lait Cerave',
                        'type' => 'Parapharmacie',
                        'quantite' => 1,
                        'prix_unitaire' => 2000,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $commande->refresh();

        $this->assertSame(6000.0, (float) $commande->prix_medicaments);
        $this->assertSame(2000.0, (float) $commande->prix_parapharma);
        $this->assertSame(8000.0, (float) $commande->prix_total);
    }

    public function test_recu_is_available_for_delivered_commande(): void
    {
        $this->seedRoles();
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();
        $client = $this->createClient();
        $commande = $this->createCommande($client, $pharmacie, [
            'status' => 'retiree',
        ]);

        $this->actingAs($admin)
            ->get("/commandes/{$commande->id}/recu")
            ->assertOk();
    }

    public function test_admin_show_json_includes_pieces_jointes(): void
    {
        Storage::fake('local');
        $this->seedRoles();

        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();
        $client = $this->createClient();
        $commande = $this->createCommande($client, $pharmacie, [
            'status' => 'en_attente',
        ]);

        $gerant = User::factory()->create(['pharmacie_id' => $pharmacie->id]);
        $gerant->assignRole('gerant');

        $file = UploadedFile::fake()->image('colis.jpg', 400, 400);
        $this->actingAs($gerant)
            ->post("/dok-pharma/{$commande->id}/pieces-jointes", [
                'fichier' => $file,
                'label' => 'Photo colis',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->getJson("/commandes/{$commande->id}")
            ->assertOk()
            ->assertJsonPath('commande.pieces_jointes.0.label', 'Photo colis')
            ->assertJsonStructure([
                'commande' => [
                    'pieces_jointes' => [
                        ['id', 'file_url', 'label'],
                    ],
                ],
            ]);
    }
}
