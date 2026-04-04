<?php

namespace Database\Seeders;

use App\Models\Heur;
use App\Models\Pharmacie;
use App\Models\TypePharmacie;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class PharmacieSeeder extends Seeder
{
    public function run(): void
    {
        $heurs = Heur::where('ouverture', '08:00')->where('fermeture', '20:00')->first()
            ?? Heur::query()->orderBy('id')->first();
        $heursClairon = Heur::where('ouverture', '09:00')->where('fermeture', '19:00')->first()
            ?? $heurs;
        $typeJour = TypePharmacie::where('designation', 'Pharmacie de Jour')->first();
        $typeNuit = TypePharmacie::where('designation', 'like', '%nuit%')->first();
        $zone1 = Zone::query()->orderBy('id')->first();

        if (! $zone1 || ! $heurs || ! $typeJour) {
            return;
        }

        Pharmacie::firstOrCreate(
            ['designation' => 'Pharmacie Clairon'],
            [
                'zone_id' => $zone1->id,
                'type_pharmacie_id' => $typeJour->id,
                'heurs_id' => $heursClairon->id,
                'telephone' => '+242 06 982 2424',
                'adresse' => 'CAMP CLAIRON',
                'latitude' => $zone1->latitude ? (float) $zone1->latitude + 0.002 : -4.2694,
                'longitude' => $zone1->longitude ? (float) $zone1->longitude + 0.001 : 15.2712,
                'email' => 'info@clairon.com',
                'de_garde' => true,
                'proprio_nom' => 'Dr. Jean Paul Moukolo',
                'proprio_tel' => '+242 06 854 2121',
                'proprio_email' => 'info@clairon.com',
            ]
        );

        $idx = 0;
        foreach (Zone::all() as $zone) {
            $offset = $idx * 0.003;
            Pharmacie::firstOrCreate(
                ['designation' => "Pharmacie {$zone->designation}", 'zone_id' => $zone->id],
                [
                    'type_pharmacie_id' => ($idx % 2 === 0) ? $typeJour->id : ($typeNuit ?? $typeJour)->id,
                    'heurs_id' => $heurs->id,
                    'telephone' => '+242 01 234 56 78',
                    'adresse' => "Avenue principale, {$zone->designation}",
                    'latitude' => $zone->latitude ? (float) $zone->latitude + ($offset * 0.5) : null,
                    'longitude' => $zone->longitude ? (float) $zone->longitude + ($offset * 0.3) : null,
                    'email' => "pharma.{$zone->id}@bengadok.cg",
                    'proprio_nom' => 'Dr. Propriétaire',
                ]
            );
            $idx++;
        }
    }
}
