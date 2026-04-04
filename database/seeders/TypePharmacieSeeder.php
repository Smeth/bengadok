<?php

namespace Database\Seeders;

use App\Models\Heur;
use App\Models\TypePharmacie;
use Illuminate\Database\Seeder;

class TypePharmacieSeeder extends Seeder
{
    public function run(): void
    {
        $heurJour = Heur::where('ouverture', '08:00')->where('fermeture', '20:00')->first()
            ?? Heur::query()->orderBy('id')->first();
        $heurNuit = Heur::where('ouverture', '19:00')->where('fermeture', '08:00')->first()
            ?? $heurJour;

        if (! $heurJour) {
            return;
        }

        TypePharmacie::firstOrCreate(
            ['designation' => 'Pharmacie de Jour'],
            ['heurs_id' => $heurJour->id]
        );
        TypePharmacie::firstOrCreate(
            ['designation' => 'Pharmacie de Garde'],
            ['heurs_id' => $heurJour->id]
        );
        TypePharmacie::firstOrCreate(
            ['designation' => 'Pharmacie de nuit'],
            ['heurs_id' => $heurNuit->id]
        );
    }
}
