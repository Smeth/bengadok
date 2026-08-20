<?php

namespace Database\Seeders;

use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clientsData = [
            ['nom' => 'Diallo', 'prenom' => 'Amélia', 'tel' => '+242 07 111 11 11', 'adresse' => 'Quartier Moungali', 'sexe' => 'F', 'arrondissement' => 'Moungali'],
            ['nom' => 'Kouadio', 'prenom' => 'Ludovic', 'tel' => '+242 07 222 22 22', 'adresse' => 'Avenue Poto-Poto', 'sexe' => 'M', 'arrondissement' => 'Poto-Poto'],
            ['nom' => 'Traoré', 'prenom' => 'Louis', 'tel' => '+242 07 333 33 33', 'adresse' => 'Rue Bacongo', 'sexe' => 'M', 'arrondissement' => 'Bacongo'],
            ['nom' => "Mig's", 'prenom' => 'Marc', 'tel' => '+242 06 952 67 31', 'adresse' => '16 rue Djouari moukounziguaka, Poto-Poto', 'sexe' => 'M', 'arrondissement' => 'Poto-Poto'],
            ['nom' => "Mig's", 'prenom' => 'Marc', 'tel' => '+242 06 513 23 78', 'adresse' => '16 rue Djouari moukounziguaka, Poto-Poto', 'sexe' => 'M', 'arrondissement' => 'Poto-Poto'],
            ['nom' => 'Dupont', 'prenom' => 'Marie', 'tel' => '+242 06 700 11 11', 'adresse' => 'Avenue Charles de Gaulle, Poto-Poto', 'sexe' => 'F', 'arrondissement' => 'Poto-Poto'],
            ['nom' => 'Dupont', 'prenom' => 'Marie', 'tel' => '+242 06 700 22 22', 'adresse' => 'Avenue Charles de Gaulle, Poto-Poto', 'sexe' => 'F', 'arrondissement' => 'Poto-Poto'],
        ];

        foreach ($clientsData as $c) {
            $client = Client::firstOrCreate(
                ['tel' => $c['tel']],
                array_merge($c, ['client_depuis' => now()->subMonths(4)])
            );
            if (! $client->arrondissement && ($c['arrondissement'] ?? null)) {
                $client->update([
                    'arrondissement' => $c['arrondissement'],
                    'client_depuis' => $client->client_depuis ?? $client->created_at,
                ]);
            }
        }

        // Marc Mig's principal – créé le 15/10/2025
        $marcPrincipal = Client::where('tel', '+242 06 952 67 31')->first();
        if ($marcPrincipal) {
            $marcPrincipal->update([
                'client_depuis' => Carbon::create(2025, 10, 15),
                'adresse' => '16 rue Djouari moukounziguaka, Poto-Poto',
                'arrondissement' => 'Poto-Poto',
            ]);
            $marcPrincipal->created_at = Carbon::create(2025, 10, 15);
            $marcPrincipal->saveQuietly();
        }

        // Marc Mig's dupliqué – créé le 16/02/2026
        $marcDuplique = Client::where('tel', '+242 06 513 23 78')->first();
        if ($marcDuplique) {
            $marcDuplique->update([
                'client_depuis' => Carbon::create(2026, 2, 16),
                'arrondissement' => 'Poto-Poto',
            ]);
            $marcDuplique->created_at = Carbon::create(2026, 2, 16);
            $marcDuplique->saveQuietly();
        }
    }
}
