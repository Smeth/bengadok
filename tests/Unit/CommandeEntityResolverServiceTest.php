<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Heur;
use App\Models\Pharmacie;
use App\Models\TypePharmacie;
use App\Services\CommandeEntityResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\TestCase;

class CommandeEntityResolverServiceTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;

    public function test_parse_client_name_extracts_civility_and_name(): void
    {
        $service = app(CommandeEntityResolverService::class);

        $parsed = $service->parseClientName('Madame ELEBE');

        $this->assertSame('F', $parsed['sexe']);
        $this->assertSame('ELEBE', $parsed['nom']);
    }

    public function test_normalize_arrondissement_matches_official_list(): void
    {
        $service = app(CommandeEntityResolverService::class);

        $this->assertSame('Makélékélé', $service->normalizeArrondissement('Makélékélé'));
        $this->assertSame('Poto-Poto', $service->normalizeArrondissement('Poto-Poto'));
    }

    public function test_resolve_client_accepts_null_tel_on_create(): void
    {
        $service = app(CommandeEntityResolverService::class);

        $client = $service->resolveClient([
            'client_nom' => 'SansTel',
            'client_prenom' => null,
            'client_tel' => null,
            'client_adresse' => null,
            'client_arrondissement' => null,
            'client_sexe' => null,
        ]);

        $this->assertSame('', $client->tel);
        $this->assertSame('', $client->adresse);
    }

    public function test_find_client_by_tel_reuses_existing_prospect(): void
    {
        $service = app(CommandeEntityResolverService::class);
        $existing = Client::query()->create([
            'nom' => 'Martin',
            'tel' => '06 686 79 72',
            'adresse' => 'Adresse',
        ]);

        $client = $service->resolveClient([
            'client_nom' => 'ELEBE',
            'client_prenom' => null,
            'client_tel' => '06 686 79 72',
            'client_adresse' => 'Nouvelle adresse',
            'client_arrondissement' => 'Moungali',
            'client_sexe' => 'F',
        ]);

        $this->assertSame($existing->id, $client->id);
        $this->assertSame('Moungali', $client->arrondissement);
    }

    public function test_resolve_pharmacie_matches_partial_designation(): void
    {
        $service = app(CommandeEntityResolverService::class);
        $pharmacie = $this->createPharmacie(null, ['designation' => 'Pharmacie Clairon']);

        $resolved = $service->resolvePharmacie(null, 'Clairon', 'Moungali');

        $this->assertSame($pharmacie->id, $resolved->id);
    }

    public function test_parse_produit_lines_splits_multi_product_text(): void
    {
        $service = app(CommandeEntityResolverService::class);

        $lines = $service->parseProduitLinesFromLegacyRow([
            'medicaments' => 'Stilnox boite x2 + Temesta 2,5mg',
            'quantite' => 1,
            'montant_produits' => 4000,
        ]);

        $this->assertCount(2, $lines);
        $this->assertSame('Stilnox boite', $lines[0]['designation']);
        $this->assertSame(2, $lines[0]['quantite']);
        $this->assertSame('Temesta 2,5mg', $lines[1]['designation']);
    }

    public function test_resolve_pharmacie_creates_missing_pharmacy(): void
    {
        $service = app(CommandeEntityResolverService::class);
        $this->createZone('Zone import Excel');
        $heur = Heur::query()->create(['ouverture' => '08:00', 'fermeture' => '18:00']);
        TypePharmacie::query()->create(['designation' => 'Standard', 'heurs_id' => $heur->id]);

        $this->assertNull(Pharmacie::query()->where('designation', 'like', '%Auréole%')->first());

        $resolved = $service->resolvePharmacie(null, 'Auréole', 'Poto-Poto');

        $this->assertStringContainsString('Auréole', $resolved->designation);
        $this->assertFalse($resolved->est_partenaire);
    }
}
