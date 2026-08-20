<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Active/désactive par pharmacie les fonctionnalités crédits + commission parapharmacie
     * (masque les blocs correspondants côté dashboard pharmacie et empêche toute déduction).
     */
    public function up(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->boolean('credits_actif')->default(true)->after('credits_alerte_seuil');
        });
    }

    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropColumn('credits_actif');
        });
    }
};
