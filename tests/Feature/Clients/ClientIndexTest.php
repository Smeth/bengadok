<?php

namespace Tests\Feature\Clients;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalFixtures;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class ClientIndexTest extends TestCase
{
    use CreatesMinimalFixtures;
    use RefreshDatabase;
    use SeedsRoles;

    public function test_clients_index_is_paginated_and_includes_aggregated_stats(): void
    {
        $admin = $this->userWithRole('admin');
        $pharmacie = $this->createPharmacie();

        $promu = $this->createClient([
            'nom' => 'Martin',
            'prenom' => 'Alice',
            'promu_client_le' => now(),
        ]);
        $this->createCommande($promu, $pharmacie, ['status' => 'retiree', 'prix_total' => 10000]);
        $this->createCommande($promu, $pharmacie, ['status' => 'retiree', 'prix_total' => 5000]);

        $this->createClient(['promu_client_le' => null]);

        $response = $this->actingAs($admin)->get('/clients');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data', 1)
            ->where('clients.total', 1)
            ->where('clients.data.0.nb_commandes', 2)
            ->where('clients.data.0.total_depense', 15000)
        );
    }
}
