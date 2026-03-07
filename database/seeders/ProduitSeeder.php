<?php

namespace Database\Seeders;

use App\Models\Pharmacie;
use App\Models\Produit;
use Illuminate\Database\Seeder;

class ProduitSeeder extends Seeder
{
    public function run(): void
    {
        $produits = [
            ['designation' => 'Doliprane', 'dosage' => '1000 mg', 'forme' => 'Effervescent', 'pu' => 1300, 'type' => 'Vente libre'],
            ['designation' => 'Paracétamol', 'dosage' => '500mg', 'forme' => 'Comprimé', 'pu' => 500, 'type' => 'Vente libre'],
            ['designation' => 'Oméprazole', 'dosage' => '20mg', 'forme' => 'Gélule', 'pu' => 1500, 'type' => 'Vente libre'],
            ['designation' => 'Ibuprofène', 'dosage' => '400mg', 'forme' => 'Comprimé', 'pu' => 800, 'type' => 'Vente libre'],
            ['designation' => 'Temesta', 'dosage' => '2.5mg', 'forme' => 'Comprimé', 'pu' => 5550, 'type' => 'Sur ordonnance'],
            ['designation' => 'Antalgex', 'forme' => 'Sirop', 'pu' => 1600, 'type' => 'Vente libre'],
            ['designation' => 'Vivagest', 'forme' => 'Gélule', 'pu' => 3500, 'type' => 'Vente libre'],
            ['designation' => 'Glibenclamide', 'dosage' => '5mg', 'forme' => 'Comprimé', 'pu' => 2500, 'type' => 'Sur ordonnance'],
            ['designation' => 'Levothyrox', 'dosage' => '50µg', 'forme' => 'Comprimé', 'pu' => 4000, 'type' => 'Sur ordonnance'],
            ['designation' => 'Aldactone', 'dosage' => '50mg', 'forme' => 'Comprimé', 'pu' => 3500, 'type' => 'Sur ordonnance'],
            ['designation' => 'Clipal', 'dosage' => '300mg', 'forme' => 'Comprimé', 'pu' => 2800, 'type' => 'Sur ordonnance'],
        ];

        $pharmacies = Pharmacie::all();

        foreach ($produits as $p) {
            $produit = Produit::updateOrCreate(
                ['designation' => $p['designation']],
                array_merge($p, ['dosage' => $p['dosage'] ?? null, 'forme' => $p['forme'] ?? null, 'type' => $p['type'] ?? 'Vente libre'])
            );
            foreach ($pharmacies as $pharma) {
                $variation = rand(-10, 30) / 100;
                $prixPharma = round($produit->pu * (1 + $variation));
                $produit->pharmacies()->syncWithoutDetaching([
                    $pharma->id => ['stock' => rand(10, 100), 'prix' => max(100, $prixPharma)],
                ]);
            }
        }
    }
}
