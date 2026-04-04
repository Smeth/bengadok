<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BengaDokSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ApplicationDefaultsSeeder::class,
            ZoneSeeder::class,
            HeurSeeder::class,
            TypePharmacieSeeder::class,
            PharmacieSeeder::class,
            ProduitSeeder::class,
            ModePaiementSeeder::class,
            MontantLivraisonSeeder::class,
            LivreurSeeder::class,
            ClientSeeder::class,
            CommandeSeeder::class,
            UserSeeder::class,
        ]);
    }
}
