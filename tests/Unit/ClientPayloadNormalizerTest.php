<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Support\ClientPayloadNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPayloadNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_attributes_from_payload_coerces_empty_tel_and_adresse(): void
    {
        $attrs = ClientPayloadNormalizer::attributesFromCommandePayload([
            'client_nom' => 'Dupont',
            'client_prenom' => 'Jean',
            'client_tel' => null,
            'client_adresse' => '',
        ]);

        $this->assertSame('', $attrs['tel']);
        $this->assertSame('', $attrs['adresse']);
    }

    public function test_attributes_from_payload_skips_missing_keys_when_only_present(): void
    {
        $attrs = ClientPayloadNormalizer::attributesFromCommandePayload([
            'client_tel' => null,
        ], onlyPresentKeys: true);

        $this->assertSame(['tel' => ''], $attrs);
    }

    public function test_client_model_saving_coerces_null_tel_and_adresse(): void
    {
        $client = Client::query()->create([
            'nom' => 'Test',
            'tel' => null,
            'adresse' => null,
        ]);

        $this->assertSame('', $client->tel);
        $this->assertSame('', $client->adresse);
    }
}
