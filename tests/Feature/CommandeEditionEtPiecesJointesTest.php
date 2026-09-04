<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommandePieceJointe;
use App\Models\MontantLivraison;
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
                'montant_livraison_id' => true,
            ],
        ]);

        $this->seedRoles();
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();
        $montant = MontantLivraison::query()->create(['designation' => 1500]);

        $payload = [
            'pharmacie_id' => $pharmacie->id,
            'client_prenom' => 'Paul',
            'client_tel' => '0611223344',
            'client_adresse' => '12 rue test',
            'client_arrondissement' => Client::ARRONDISSEMENTS[0],
            'date' => '2020-01-01',
            'heurs' => '08:00',
            'montant_livraison_id' => $montant->id,
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
        $this->assertSame($montant->id, (int) $commande->montant_livraison_id);
    }
}
