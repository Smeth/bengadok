<?php

namespace Database\Seeders;

use App\Models\Heur;
use App\Models\Pharmacie;
use App\Models\TypePharmacie;
use App\Models\Zone;
use Illuminate\Database\Seeder;

/**
 * Pharmacies fréquentes dans les fichiers Excel importés (matching fuzzy à l'intégration).
 */
class ImportPharmacieSeeder extends Seeder
{
    public function run(): void
    {
        $zone = Zone::query()->orderBy('id')->first();
        $heur = Heur::query()->orderBy('id')->first();
        $type = TypePharmacie::query()->orderBy('id')->first();

        if (! $zone || ! $heur || ! $type) {
            return;
        }

        $designations = [
            'Pharmacie Auréole',
            'Pharmacie Clairon',
            'Pharmacie Vander Veecken',
            'Pharmacie Adèle',
            'Pharmacie Bethléem',
            'Pharmacie Béatitude',
            'Pharmacie Christ-Roi',
            'Pharmacie Cristale',
            'Pharmacie Daffé',
            'Pharmacie Jagger',
            'Pharmacie La Patience',
            'Pharmacie Mavré',
            'Pharmacie Pont Centenaire',
            'Pharmacie Pro Pharma',
            'Pharmacie Renande & Maat',
            'Pharmacie Rosel',
            'Pharmacie Brant Jynes',
        ];

        foreach ($designations as $designation) {
            Pharmacie::firstOrCreate(
                ['designation' => $designation],
                [
                    'zone_id' => $zone->id,
                    'type_pharmacie_id' => $type->id,
                    'heurs_id' => $heur->id,
                    'telephone' => '000000000',
                    'adresse' => 'Adresse à compléter',
                    'proprio_nom' => 'À compléter',
                    'note_interne' => 'Référentiel import Excel — données à compléter.',
                ],
            );
        }
    }
}
