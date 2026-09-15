<?php

namespace Tests\Unit;

use App\Models\DbCommande;
use App\Services\DbCommandeImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DbCommandeImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_statut_import_value_matches_system_statuses(): void
    {
        $this->assertSame('nouvelle', DbCommande::parseStatutImportValue('Nouvelle'));
        $this->assertSame('en_attente', DbCommande::parseStatutImportValue('En attente'));
        $this->assertSame('validee', DbCommande::parseStatutImportValue('Validée'));
        $this->assertSame('retiree', DbCommande::parseStatutImportValue('LIVRÉE'));
        $this->assertSame('annulee', DbCommande::parseStatutImportValue('Annulée'));
        $this->assertNull(DbCommande::parseStatutImportValue('Statut inconnu'));
    }

    public function test_map_imported_row_normalizes_statut(): void
    {
        $service = app(DbCommandeImportService::class);

        $payload = $service->mapImportedRow([
            'Statut' => 'LIVRÉE',
            'Nom du Client' => 'Madame Test',
            'Médicaments Commandés' => 'Vitamine C',
        ]);

        $this->assertNotNull($payload);
        $this->assertSame('retiree', $payload['statut']);
        $this->assertSame('Madame Test', $payload['nom_client']);
    }

    public function test_map_imported_row_parses_fcfa_amounts_with_thousands_separator(): void
    {
        $service = app(DbCommandeImportService::class);

        $payload = $service->mapImportedRow([
            'Montant Produits' => '3,400 FCFA',
            'Frais Livraison' => '1,000 FCFA',
            'Total Payé Client' => '4,400 FCFA',
            'CA Parapharmacie' => '0 FCFA',
            'Nom du Client' => 'Madame Test',
            'Médicaments Commandés' => 'Vitamine C',
        ]);

        $this->assertNotNull($payload);
        $this->assertSame(3400.0, $payload['montant_produits']);
        $this->assertSame(1000.0, $payload['frais_livraison']);
        $this->assertSame(4400.0, $payload['total_paye_client']);
        $this->assertSame(0.0, $payload['ca_parapharmacie']);
    }

    public function test_map_imported_row_derives_montant_from_ca_columns(): void
    {
        $service = app(DbCommandeImportService::class);

        $payload = $service->mapImportedRow([
            'CA Médicaments' => '3 000',
            'CA Parapharmacie' => '1 500',
            'Nom du Client' => 'Madame Test',
            'Médicaments Commandés' => 'Doliprane',
        ]);

        $this->assertNotNull($payload);
        $this->assertSame(4500.0, $payload['montant_produits']);
    }

    public function test_map_imported_row_reads_pharmacie_from_pharmacie_s_header(): void
    {
        $service = app(DbCommandeImportService::class);

        $payload = $service->mapImportedRow([
            'Pharmacie(s)' => 'Clairon',
            'Nom du Client' => 'Madame Test',
            'Médicaments Commandés' => 'Vitamine C',
        ]);

        $this->assertNotNull($payload);
        $this->assertSame('Clairon', $payload['pharmacie']);
        $this->assertArrayNotHasKey('semaine', $payload);
        $this->assertArrayNotHasKey('montant_du_theorique', $payload);
        $this->assertArrayNotHasKey('motif_perte', $payload);
        $this->assertArrayNotHasKey('notes', $payload);
    }

    public function test_import_excel_file_creates_staging_rows(): void
    {
        $service = app(DbCommandeImportService::class);
        $path = storage_path('app/temp/test-db-commandes-import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $service->writeModeleExcel($path);
        $result = $service->importExcelPath($path, true);

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('db_commandes', [
            'code_commande' => 'BDK0101-001',
            'nom_client' => 'Madame Exemple',
        ]);

        @unlink($path);
    }

    public function test_write_imports_excel_exports_staging_rows(): void
    {
        $service = app(DbCommandeImportService::class);

        DbCommande::query()->create([
            'code_commande' => 'BDK0101-099',
            'nom_client' => 'Client Export',
            'medicaments' => 'Test',
            'statut' => 'livree',
            'source' => DbCommande::SOURCE_IMPORT,
            'empreinte' => hash('sha256', 'test-export'),
        ]);

        $path = storage_path('app/temp/test-db-commandes-export.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $service->writeImportsExcel($path);

        $this->assertFileExists($path);

        $sheet = IOFactory::load($path)->getSheetByName('Commandes');
        $this->assertNotNull($sheet);
        $this->assertSame('BDK0101-099', $sheet->getCell('B2')->getValue());
        $this->assertSame('Client Export', $sheet->getCell('E2')->getValue());
        $this->assertSame('W', $sheet->getHighestDataColumn());

        @unlink($path);
    }
}
