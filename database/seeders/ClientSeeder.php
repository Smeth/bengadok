<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $zoneMoungali = Zone::where('designation', 'Moungali')->first();
        $zonePotoPoto = Zone::where('designation', 'Poto-Poto')->first();
        $zoneBacongo = Zone::where('designation', 'Bacongo')->first();

        $clientsData = [
            ['nom' => 'Diallo', 'prenom' => 'Amélia', 'tel' => '+242 07 111 11 11', 'adresse' => 'Quartier Moungali', 'sexe' => 'F', 'zone_id' => $zoneMoungali?->id],
            ['nom' => 'Kouadio', 'prenom' => 'Ludovic', 'tel' => '+242 07 222 22 22', 'adresse' => 'Avenue Poto-Poto', 'sexe' => 'M', 'zone_id' => $zonePotoPoto?->id],
            ['nom' => 'Traoré', 'prenom' => 'Louis', 'tel' => '+242 07 333 33 33', 'adresse' => 'Rue Bacongo', 'sexe' => 'M', 'zone_id' => $zoneBacongo?->id],
            ['nom' => "Mig's", 'prenom' => 'Marc', 'tel' => '+242 06 952 67 31', 'adresse' => '16 rue Djouari moukounziguaka, Poto-Poto', 'sexe' => 'M', 'zone_id' => $zonePotoPoto?->id],
            ['nom' => "Mig's", 'prenom' => 'Marc', 'tel' => '+242 06 513 23 78', 'adresse' => '16 rue Djouari moukounziguaka, Poto-Poto', 'sexe' => 'M', 'zone_id' => $zonePotoPoto?->id],
            ['nom' => 'Dupont', 'prenom' => 'Marie', 'tel' => '+242 06 700 11 11', 'adresse' => 'Avenue Charles de Gaulle, Poto-Poto', 'sexe' => 'F', 'zone_id' => $zonePotoPoto?->id],
            ['nom' => 'Dupont', 'prenom' => 'Marie', 'tel' => '+242 06 700 22 22', 'adresse' => 'Avenue Charles de Gaulle, Poto-Poto', 'sexe' => 'F', 'zone_id' => $zonePotoPoto?->id],
        ];

        foreach ($clientsData as $c) {
            $client = Client::firstOrCreate(
                ['tel' => $c['tel']],
                array_merge($c, ['client_depuis' => now()->subMonths(4)])
            );
            if (! $client->zone_id && ($c['zone_id'] ?? null)) {
                $client->update(['zone_id' => $c['zone_id'], 'client_depuis' => $client->client_depuis ?? $client->created_at]);
            }
        }

        // Marc Mig's principal – créé le 15/10/2025
        $marcPrincipal = Client::where('tel', '+242 06 952 67 31')->first();
        if ($marcPrincipal) {
            $marcPrincipal->update([
                'client_depuis' => Carbon::create(2025, 10, 15),
                'adresse' => '16 rue Djouari moukounziguaka, Poto-Poto',
            ]);
            $marcPrincipal->created_at = Carbon::create(2025, 10, 15);
            $marcPrincipal->saveQuietly();
        }

        // Marc Mig's dupliqué – créé le 16/02/2026
        $marcDuplique = Client::where('tel', '+242 06 513 23 78')->first();
        if ($marcDuplique) {
            $marcDuplique->update(['client_depuis' => Carbon::create(2026, 2, 16)]);
            $marcDuplique->created_at = Carbon::create(2026, 2, 16);
            $marcDuplique->saveQuietly();
        }
    }
}
