<?php

namespace Tests\Feature\DbCommandes;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DbCommande;
use App\Models\User;
use App\Services\DbCommandeImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class DbCommandeModuleTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_admin_can_view_db_commandes_index_with_referentiels(): void
    {
        $admin = $this->userWithRole('admin');

        DbCommande::query()->create([
            'code_commande' => 'BDK0101-001',
            'nom_client' => 'Client A',
            'medicaments' => 'Test',
            'statut' => 'retiree',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-1'),
        ]);

        $this->actingAs($admin)
            ->get(route('db-commandes.index', ['tab' => 'imports']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DbCommandes/Index')
                ->has('imports.data', 1)
                ->has('referentiels.pharmacies')
            );

        $this->actingAs($admin)
            ->get(route('db-commandes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DbCommandes/Index')
                ->where('tab', 'commandes')
                ->has('commandes.data')
            );
    }

    public function test_admin_can_integrate_staged_row_into_live_commande(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createPharmacie(null, ['designation' => 'Pharmacie Auréole']);

        $row = DbCommande::query()->create([
            'code_commande' => 'BDK0101-002',
            'nom_client' => 'Madame Test',
            'sexe' => 'F',
            'telephone' => '06 111 22 33',
            'adresse_livraison' => 'Centre-ville',
            'arrondissement' => 'Poto-Poto',
            'pharmacie' => 'Auréole',
            'medicaments' => 'Paracétamol 500 mg',
            'quantite' => 1,
            'mode_paiement' => 'Espèces',
            'montant_produits' => 2500,
            'frais_livraison' => 1000,
            'total_paye_client' => 3500,
            'statut' => 'retiree',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-2'),
        ]);

        $this->actingAs($admin)
            ->post(route('db-commandes.integrate', $row))
            ->assertRedirect(route('db-commandes.index', ['tab' => 'imports']));

        $row->refresh();
        $this->assertNotNull($row->commande_id);
        $this->assertDatabaseHas('commandes', [
            'id' => $row->commande_id,
            'numero' => 'BDK0101-002',
            'status' => 'retiree',
            'status_pharmacie' => 'livre',
        ]);
        $this->assertDatabaseHas('clients', [
            'tel' => '06 111 22 33',
        ]);
    }

    public function test_agent_can_view_db_commandes_but_not_import_tab(): void
    {
        $agent = $this->userWithRole('agent_call_center');

        $this->actingAs($agent)
            ->get(route('db-commandes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManageImports', false)
                ->where('canDeleteCommandes', false)
            );

        $this->actingAs($agent)
            ->get(route('db-commandes.index', ['tab' => 'imports']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tab', 'commandes'));
    }

    public function test_vendeur_cannot_access_db_commandes(): void
    {
        $vendeur = $this->userWithRole('vendeur');

        $this->actingAs($vendeur)
            ->get(route('db-commandes.index'))
            ->assertRedirect('/dok-pharma/commandes');
    }

    public function test_admin_can_retry_failed_integration(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createPharmacie(null, ['designation' => 'Pharmacie Auréole']);

        $row = DbCommande::query()->create([
            'code_commande' => 'BDK0101-RETRY',
            'nom_client' => 'Client Retry',
            'telephone' => '06 222 33 44',
            'pharmacie' => 'Auréole',
            'medicaments' => 'Paracétamol',
            'montant_produits' => 1500,
            'statut' => 'retiree',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-retry'),
            'integration_error' => 'Échec précédent simulé',
        ]);

        $this->actingAs($admin)
            ->post(route('db-commandes.integrate', $row))
            ->assertRedirect(route('db-commandes.index', ['tab' => 'imports']));

        $row->refresh();
        $this->assertNotNull($row->commande_id);
        $this->assertNull($row->integration_error);
    }

    public function test_admin_can_bulk_delete_commandes_and_reset_db_commande_link(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();
        $client = $this->createClient();
        $commande = $this->createCommande($client, $pharmacie, ['numero' => 'BDK-DEL-001']);

        $row = DbCommande::query()->create([
            'code_commande' => 'BDK0101-DEL',
            'commande_id' => $commande->id,
            'integrated_at' => now(),
            'nom_client' => 'Client Del',
            'medicaments' => 'Test',
            'statut' => 'retiree',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-del'),
        ]);

        $this->actingAs($admin)
            ->post(route('db-commandes.commandes.destroy-bulk'), ['ids' => [$commande->id]])
            ->assertRedirect(route('db-commandes.index', ['tab' => 'commandes']));

        $this->assertDatabaseMissing('commandes', ['id' => $commande->id]);
        $row->refresh();
        $this->assertNull($row->commande_id);
        $this->assertNull($row->integrated_at);
        $this->assertNull($row->integration_error);
    }

    public function test_admin_can_export_commandes_excel(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();
        $client = $this->createClient();
        $this->createCommande($client, $pharmacie);

        $this->actingAs($admin)
            ->get(route('db-commandes.export'))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
    }

    public function test_admin_can_export_imports_as_excel(): void
    {
        $admin = $this->userWithRole('admin');

        DbCommande::query()->create([
            'code_commande' => 'BDK-EXPORT-001',
            'nom_client' => 'Client Export',
            'medicaments' => 'Test',
            'statut' => 'retiree',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-export-feature'),
        ]);

        $this->actingAs($admin)
            ->get(route('db-commandes.export-imports'))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
    }

    public function test_imports_index_filters_failed_integration_rows(): void
    {
        $admin = $this->userWithRole('admin');

        DbCommande::query()->create([
            'code_commande' => 'BDK-FAIL',
            'nom_client' => 'Client Fail',
            'medicaments' => 'Test',
            'statut' => 'retiree',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-fail'),
            'integration_error' => 'Pharmacie introuvable',
        ]);

        DbCommande::query()->create([
            'code_commande' => 'BDK-OK-PEND',
            'nom_client' => 'Client Pend',
            'medicaments' => 'Test',
            'statut' => 'retiree',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-pend'),
        ]);

        $this->actingAs($admin)
            ->get(route('db-commandes.index', [
                'tab' => 'imports',
                'etat_integration' => 'failed',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('imports.data', 1)
                ->where('imports.data.0.code_commande', 'BDK-FAIL')
            );
    }

    public function test_admin_can_import_excel_file_via_http(): void
    {
        $admin = $this->userWithRole('admin');
        $path = storage_path('app/temp/test-http-import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        app(DbCommandeImportService::class)->writeModeleExcel($path);

        $this->actingAs($admin)
            ->post(route('db-commandes.import'), [
                'file' => new UploadedFile($path, 'import.xlsx', null, null, true),
                'skip_duplicates' => true,
            ])
            ->assertRedirect(route('db-commandes.index', ['tab' => 'imports']));

        $this->assertDatabaseHas('db_commandes', [
            'code_commande' => 'BDK0101-001',
            'statut' => 'retiree',
        ]);

        @unlink($path);
    }

    public function test_admin_can_integrate_all_pending_creates_pharmacies_clients_and_promotes_delivered(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createZone('Poto-Poto');
        $heur = \App\Models\Heur::query()->create(['ouverture' => '08:00', 'fermeture' => '18:00']);
        \App\Models\TypePharmacie::query()->create(['designation' => 'Standard', 'heurs_id' => $heur->id]);

        $existingProspect = $this->createClient([
            'nom' => 'Test',
            'tel' => '06 111 22 33',
            'promu_client_le' => null,
        ]);

        $delivered = DbCommande::query()->create([
            'code_commande' => 'BDK-ALL-001',
            'nom_client' => 'Madame Test',
            'telephone' => '06 111 22 33',
            'adresse_livraison' => 'Centre-ville',
            'arrondissement' => 'Poto-Poto',
            'pharmacie' => 'Auréole',
            'medicaments' => 'Paracétamol 500 mg',
            'quantite' => 1,
            'montant_produits' => 2500,
            'frais_livraison' => 1000,
            'total_paye_client' => 3500,
            'statut' => 'retiree',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-all-delivered'),
        ]);

        $nouvelle = DbCommande::query()->create([
            'code_commande' => 'BDK-ALL-002',
            'nom_client' => 'Nouveau Prospect',
            'telephone' => '06 999 88 77',
            'adresse_livraison' => 'Moungali',
            'arrondissement' => 'Moungali',
            'pharmacie' => 'Auréole',
            'medicaments' => 'Vitamine C',
            'quantite' => 1,
            'montant_produits' => 1500,
            'statut' => 'nouvelle',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-all-nouvelle'),
        ]);

        $this->actingAs($admin)
            ->post(route('db-commandes.integrate-all'))
            ->assertRedirect(route('db-commandes.index', ['tab' => 'imports']));

        $delivered->refresh();
        $nouvelle->refresh();

        $this->assertNotNull($delivered->commande_id);
        $this->assertNotNull($nouvelle->commande_id);

        $this->assertDatabaseHas('pharmacies', [
            'designation' => 'Pharmacie Auréole',
            'est_partenaire' => false,
        ]);

        $this->assertSame($existingProspect->id, Commande::query()->find($delivered->commande_id)?->client_id);
        $this->assertNotNull($existingProspect->fresh()->promu_client_le);

        $createdProspect = \App\Models\Client::query()->where('tel', '06 999 88 77')->first();
        $this->assertNotNull($createdProspect);
        $this->assertNull($createdProspect->promu_client_le);
        $this->assertSame($createdProspect->id, Commande::query()->find($nouvelle->commande_id)?->client_id);
    }

    public function test_integrate_maps_validee_statut_to_system_statuses(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createPharmacie(null, ['designation' => 'Pharmacie Auréole']);

        $row = DbCommande::query()->create([
            'code_commande' => 'BDK-VALID-001',
            'nom_client' => 'Client Validée',
            'telephone' => '06 333 44 55',
            'pharmacie' => 'Auréole',
            'medicaments' => 'Vitamine C',
            'montant_produits' => 2000,
            'statut' => 'validee',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-validee'),
        ]);

        $this->actingAs($admin)
            ->post(route('db-commandes.integrate', $row))
            ->assertRedirect(route('db-commandes.index', ['tab' => 'imports']));

        $row->refresh();
        $this->assertDatabaseHas('commandes', [
            'id' => $row->commande_id,
            'status' => 'validee',
            'status_pharmacie' => 'valide_a_preparer',
        ]);
    }

    public function test_integrate_unknown_statut_defaults_to_nouvelle(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createPharmacie(null, ['designation' => 'Pharmacie Auréole']);

        $row = DbCommande::query()->create([
            'code_commande' => 'BDK-UNK-001',
            'nom_client' => 'Client Inconnu',
            'telephone' => '06 444 55 66',
            'pharmacie' => 'Auréole',
            'medicaments' => 'Test',
            'montant_produits' => 1000,
            'statut' => 'statut_invente',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-unknown-statut'),
        ]);

        $this->actingAs($admin)
            ->post(route('db-commandes.integrate', $row))
            ->assertRedirect(route('db-commandes.index', ['tab' => 'imports']));

        $row->refresh();
        $this->assertDatabaseHas('commandes', [
            'id' => $row->commande_id,
            'status' => 'nouvelle',
            'status_pharmacie' => 'nouvelle',
        ]);
    }

    public function test_admin_can_purge_pending_imports(): void
    {
        $admin = $this->userWithRole('admin');

        DbCommande::query()->create([
            'code_commande' => 'BDK-PURGE-001',
            'nom_client' => 'Client Purge',
            'medicaments' => 'Test',
            'statut' => 'nouvelle',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-purge'),
        ]);

        $this->actingAs($admin)
            ->post(route('db-commandes.purge-all'), [
                'confirmation' => DbCommande::PURGE_ALL_CONFIRMATION_PHRASE,
            ])
            ->assertRedirect(route('db-commandes.index', ['tab' => 'imports']));

        $this->assertDatabaseMissing('db_commandes', [
            'code_commande' => 'BDK-PURGE-001',
        ]);
    }

    public function test_purge_pending_imports_does_not_delete_users_commandes_or_integrated_rows(): void
    {
        $admin = $this->userWithRole('admin');
        $otherUser = $this->userWithRole('agent_call_center');
        $client = $this->createClient();
        $pharmacie = $this->createPharmacie();

        $commande = $this->createCommande($client, $pharmacie, [
            'status' => 'nouvelle',
            'status_pharmacie' => 'nouvelle',
        ]);

        DbCommande::query()->create([
            'code_commande' => 'BDK-PENDING-PURGE',
            'nom_client' => 'Client Pending',
            'medicaments' => 'Test pending',
            'statut' => 'nouvelle',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-purge-pending'),
        ]);

        DbCommande::query()->create([
            'code_commande' => 'BDK-INTEGRATED-KEEP',
            'nom_client' => 'Client Integrated',
            'medicaments' => 'Test integrated',
            'statut' => 'retiree',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-purge-integrated'),
            'commande_id' => $commande->id,
            'integrated_at' => now(),
        ]);

        $usersBefore = User::query()->count();
        $commandesBefore = Commande::query()->count();

        $this->actingAs($admin)
            ->post(route('db-commandes.purge-all'), [
                'confirmation' => DbCommande::PURGE_ALL_CONFIRMATION_PHRASE,
            ])
            ->assertRedirect(route('db-commandes.index', ['tab' => 'imports']));

        $this->assertDatabaseMissing('db_commandes', [
            'code_commande' => 'BDK-PENDING-PURGE',
        ]);
        $this->assertDatabaseHas('db_commandes', [
            'code_commande' => 'BDK-INTEGRATED-KEEP',
            'commande_id' => $commande->id,
        ]);
        $this->assertDatabaseHas('commandes', ['id' => $commande->id]);
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($commandesBefore, Commande::query()->count());
        $this->assertNotNull(User::query()->find($otherUser->id));
    }

    public function test_admin_can_create_commande_from_gestion_hub_with_initial_status(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();

        $this->actingAs($admin)
            ->post('/commandes', [
                'pharmacie_id' => $pharmacie->id,
                'client_prenom' => 'Marie',
                'client_tel' => '0611223344',
                'client_adresse' => 'Plateau',
                'client_arrondissement' => Client::ARRONDISSEMENTS[0],
                'produits' => [
                    [
                        'designation' => 'Doliprane',
                        'quantite' => 1,
                        'prix_unitaire' => 1500,
                    ],
                ],
                '_return_hub' => 'gestion',
                'initial_status' => 'retiree',
                'date' => '2026-03-01',
                'heurs' => '10:15',
            ])
            ->assertRedirect(route('db-commandes.index', [
                'tab' => 'commandes',
                'detail' => Commande::query()->latest('id')->value('id'),
            ]));

        $commande = Commande::query()->latest('id')->first();
        $this->assertNotNull($commande);
        $this->assertSame('retiree', $commande->status);
        $this->assertSame('2026-03-01', $commande->date->toDateString());
        $this->assertSame('10:15', substr((string) $commande->heurs, 0, 5));
        $this->assertNotNull($commande->client?->fresh()->promu_client_le);
    }

    public function test_manual_gestion_commande_with_nouvelle_status_keeps_client_as_prospect(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();

        $this->actingAs($admin)
            ->post('/commandes', [
                'pharmacie_id' => $pharmacie->id,
                'client_prenom' => 'Paul',
                'client_tel' => '0699887766',
                'client_adresse' => 'Moungali',
                'client_arrondissement' => Client::ARRONDISSEMENTS[0],
                'produits' => [
                    [
                        'designation' => 'Vitamine C',
                        'quantite' => 1,
                        'prix_unitaire' => 2000,
                    ],
                ],
                '_return_hub' => 'gestion',
                'initial_status' => 'nouvelle',
            ])
            ->assertRedirect();

        $commande = Commande::query()->latest('id')->first();
        $this->assertNotNull($commande);
        $this->assertSame('nouvelle', $commande->status);
        $this->assertNull($commande->client?->fresh()->promu_client_le);
    }

    public function test_db_commandes_index_exposes_manage_permissions_for_admin(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('db-commandes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DbCommandes/Index')
                ->where('canManageCommandes', true)
            );
    }
}
