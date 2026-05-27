<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->decimal('parapharma_commission_percent', 5, 2)->default(1)->after('delai_relance_meme_pharmacie_heures');
            $table->unsignedTinyInteger('parapharma_commission_jour_echeance')->default(25);
            $table->unsignedTinyInteger('parapharma_periode_jour_fin')->default(25);
            $table->unsignedInteger('parapharma_credit_seuil_medicament_xaf')->default(5000);
            $table->unsignedInteger('parapharma_credit_prix_unitaire_xaf')->default(150);
            $table->unsignedSmallInteger('parapharma_credit_minimum_achat')->default(10);
            $table->json('parapharma_produit_types')->nullable();
        });

        if (Schema::hasTable('app_settings')) {
            DB::table('app_settings')->update([
                'parapharma_commission_percent' => 1,
                'parapharma_commission_jour_echeance' => 25,
                'parapharma_periode_jour_fin' => 25,
                'parapharma_credit_seuil_medicament_xaf' => 5000,
                'parapharma_credit_prix_unitaire_xaf' => 150,
                'parapharma_credit_minimum_achat' => 10,
                'parapharma_produit_types' => json_encode(['Parapharmacie']),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'parapharma_commission_percent',
                'parapharma_commission_jour_echeance',
                'parapharma_periode_jour_fin',
                'parapharma_credit_seuil_medicament_xaf',
                'parapharma_credit_prix_unitaire_xaf',
                'parapharma_credit_minimum_achat',
                'parapharma_produit_types',
            ]);
        });
    }
};
