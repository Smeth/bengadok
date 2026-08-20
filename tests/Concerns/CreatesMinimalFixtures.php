<?php

namespace Tests\Concerns;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Pharmacie;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

trait CreatesMinimalFixtures
{
    protected function ensureRolesSeeded(): void
    {
        if (Role::query()->where('name', 'gerant')->exists()) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        $this->seed(RolePermissionSeeder::class);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
    protected function createZone(string $designation = 'Zone test'): Zone
    {
        return Zone::query()->create([
            'designation' => $designation,
        ]);
    }

    protected function createPharmacie(?Zone $zone = null, array $attributes = []): Pharmacie
    {
        $zone ??= $this->createZone();

        return Pharmacie::query()->create(array_merge([
            'zone_id' => $zone->id,
            'designation' => 'Pharmacie test',
            'telephone' => '0600000000',
            'adresse' => 'Adresse test',
            'credits_solde' => 0,
        ], $attributes));
    }

    protected function createClient(array $attributes = []): Client
    {
        return Client::query()->create(array_merge([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'tel' => '0612345678',
            'adresse' => 'Brazzaville',
        ], $attributes));
    }

    protected function createCommande(Client $client, Pharmacie $pharmacie, array $attributes = []): Commande
    {
        $this->ensureRolesSeeded();

        static $numero = 1;

        return Commande::query()->create(array_merge([
            'numero' => 'CMD-TEST-'.str_pad((string) $numero++, 4, '0', STR_PAD_LEFT),
            'client_id' => $client->id,
            'pharmacie_id' => $pharmacie->id,
            'status' => 'nouvelle',
            'prix_total' => 5000,
        ], $attributes));
    }
}
