<?php

namespace Database\Seeders;

use App\Models\Livreur;
use Illuminate\Database\Seeder;

class LivreurSeeder extends Seeder
{
    public function run(): void
    {
        $livreurs = [
            ['nom' => 'Mbemba', 'prenom' => 'Jean', 'tel' => '+242 06 111 22 33'],
            ['nom' => 'Nzouzi', 'prenom' => 'Pierre', 'tel' => '+242 06 222 33 44'],
        ];
        foreach ($livreurs as $l) {
            Livreur::firstOrCreate($l);
        }
    }
}
