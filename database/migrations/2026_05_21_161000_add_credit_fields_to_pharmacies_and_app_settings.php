<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->text('note_interne')->nullable()->after('credits_solde');
            $table->unsignedSmallInteger('credits_alerte_seuil')->nullable()->after('note_interne');
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('parapharma_credit_alerte_seuil')->default(5)->after('parapharma_credit_minimum_achat');
            $table->boolean('parapharma_credit_deduction_auto')->default(true)->after('parapharma_credit_alerte_seuil');
        });

        if (Schema::hasTable('app_settings')) {
            DB::table('app_settings')->update([
                'parapharma_credit_alerte_seuil' => 5,
                'parapharma_credit_deduction_auto' => true,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropColumn(['note_interne', 'credits_alerte_seuil']);
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['parapharma_credit_alerte_seuil', 'parapharma_credit_deduction_auto']);
        });
    }
};
