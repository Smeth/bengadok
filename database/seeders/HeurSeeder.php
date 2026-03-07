<?php

namespace Database\Seeders;

use App\Models\Heur;
use Illuminate\Database\Seeder;

class HeurSeeder extends Seeder
{
    public function run(): void
    {
        Heur::firstOrCreate(['ouverture' => '08:00', 'fermeture' => '20:00']);
        Heur::firstOrCreate(['ouverture' => '19:00', 'fermeture' => '08:00']);
        Heur::firstOrCreate(['ouverture' => '09:00', 'fermeture' => '19:00']);
    }
}
