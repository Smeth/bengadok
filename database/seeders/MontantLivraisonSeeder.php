<?php

namespace Database\Seeders;

use App\Models\MontantLivraison;
use Illuminate\Database\Seeder;

class MontantLivraisonSeeder extends Seeder
{
    public function run(): void
    {
        MontantLivraison::firstOrCreate(['designation' => 1000]);
        MontantLivraison::firstOrCreate(['designation' => 1500]);
        MontantLivraison::firstOrCreate(['designation' => 2000]);
    }
}
