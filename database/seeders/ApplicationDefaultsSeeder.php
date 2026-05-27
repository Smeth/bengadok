<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\OrdonnanceVerificationSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Garantit les lignes attendues par l’app (app_settings, ordonnance) avec les champs parapharma / crédits.
 */
class ApplicationDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        if (Schema::hasTable('app_settings')) {
            $parapharma = AppSetting::parapharmaDefaultsFromConfigFile();
            $payload = [
                'delai_relance_meme_pharmacie_heures' => 24,
                'parapharma_commission_percent' => $parapharma['commission_percent'],
                'parapharma_commission_jour_echeance' => $parapharma['commission_jour_echeance'],
                'parapharma_periode_jour_fin' => $parapharma['periode_jour_fin'],
                'parapharma_credit_seuil_medicament_xaf' => $parapharma['credit_seuil_medicament_xaf'],
                'parapharma_credit_prix_unitaire_xaf' => $parapharma['credit_prix_unitaire_xaf'],
                'parapharma_credit_minimum_achat' => $parapharma['credit_minimum_achat'],
                'parapharma_produit_types' => $parapharma['produit_types'],
                'parapharma_credit_alerte_seuil' => 5,
                'parapharma_credit_deduction_auto' => true,
            ];

            $row = AppSetting::query()->first();
            if ($row) {
                $row->update($payload);
            } else {
                AppSetting::query()->create($payload);
            }
        }

        if (Schema::hasTable('ordonnance_verification_settings')
            && ! OrdonnanceVerificationSetting::query()->exists()) {
            OrdonnanceVerificationSetting::query()->create([
                'enabled' => true,
                'execution_mode' => OrdonnanceVerificationSetting::EXECUTION_MODE_QUEUE,
                'max_prescription_age_days' => 180,
                'threshold_pass' => 70,
                'threshold_review' => 45,
                'rule_weights' => [
                    'date_found' => 12,
                    'date_not_future' => 20,
                    'date_within_max_age' => 25,
                    'prescriber_keywords' => 12,
                    'patient_keywords' => 8,
                    'medicament_keywords' => 13,
                    'no_duplicate_file' => 10,
                ],
                'keywords_prescriber' => [
                    'dr ', 'docteur', 'médecin', 'medecin', 'cardiologue', 'généraliste',
                    'generaliste', 'chiru', 'ophtalm', 'hôpital', 'hopital', 'centre médical',
                    'centre medical', 'clinique', 'ordonnance', 'cachet', 'stamp',
                ],
                'keywords_patient' => [
                    'patient', 'nom', 'm.', 'mme', 'mr', 'mlle', 'né le', 'ne le',
                ],
                'keywords_medicament' => [
                    'mg', 'cp', 'comprim', 'gélule', 'gelule', 'bte', 'boîte', 'boite',
                    'flacon', 'sirop', 'gttes', 'gouttes', 'inj', 'seringue', 'patch',
                ],
                'block_pass_on_duplicate' => true,
                'tesseract_binary' => 'tesseract',
            ]);
        }
    }
}
