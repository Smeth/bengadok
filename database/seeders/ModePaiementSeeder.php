<?php

namespace Database\Seeders;

use App\Models\ModePaiement;
use Illuminate\Database\Seeder;

class ModePaiementSeeder extends Seeder
{
    public function run(): void
    {
        ModePaiement::firstOrCreate(['designation' => 'Espèces']);
        ModePaiement::firstOrCreate(['designation' => 'Carte bancaire']);
        ModePaiement::firstOrCreate(['designation' => 'Mobile Money']);
    }
}
