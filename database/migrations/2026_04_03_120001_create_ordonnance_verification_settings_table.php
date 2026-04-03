<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordonnance_verification_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('max_prescription_age_days')->default(180);
            $table->unsignedTinyInteger('threshold_pass')->default(70);
            $table->unsignedTinyInteger('threshold_review')->default(45);
            $table->json('rule_weights');
            $table->json('keywords_prescriber');
            $table->json('keywords_patient');
            $table->json('keywords_medicament');
            $table->boolean('block_pass_on_duplicate')->default(true);
            $table->string('tesseract_binary', 255)->default('tesseract');
            $table->timestamps();
        });

        DB::table('ordonnance_verification_settings')->insert([
            'enabled' => true,
            'max_prescription_age_days' => 180,
            'threshold_pass' => 70,
            'threshold_review' => 45,
            'rule_weights' => json_encode([
                'date_found' => 12,
                'date_not_future' => 20,
                'date_within_max_age' => 25,
                'prescriber_keywords' => 12,
                'patient_keywords' => 8,
                'medicament_keywords' => 13,
                'no_duplicate_file' => 10,
            ]),
            'keywords_prescriber' => json_encode([
                'dr ', 'docteur', 'médecin', 'medecin', 'cardiologue', 'généraliste',
                'generaliste', 'chiru', 'ophtalm', 'hôpital', 'hopital', 'centre médical',
                'centre medical', 'clinique', 'ordonnance', 'cachet', 'stamp',
            ]),
            'keywords_patient' => json_encode([
                'patient', 'nom', 'm.', 'mme', 'mr', 'mlle', 'né le', 'ne le',
            ]),
            'keywords_medicament' => json_encode([
                'mg', 'cp', 'comprim', 'gélule', 'gelule', 'bte', 'boîte', 'boite',
                'flacon', 'sirop', 'gttes', 'gouttes', 'inj', 'seringue', 'patch',
            ]),
            'block_pass_on_duplicate' => true,
            'tesseract_binary' => 'tesseract',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ordonnance_verification_settings');
    }
};
