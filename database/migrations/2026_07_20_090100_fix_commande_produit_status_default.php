<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le code applicatif insère toujours 'en_attente' et traite l'absence de statut
     * comme 'en_attente' (voir CommandeMontantCalculator). Le défaut DB 'disponible'
     * était un piège : toute insertion pivot omettant le champ compterait la ligne
     * comme vendue par défaut.
     */
    public function up(): void
    {
        Schema::table('commande_produit', function (Blueprint $table) {
            $table->string('status', 50)->default('en_attente')->change();
        });
    }

    public function down(): void
    {
        Schema::table('commande_produit', function (Blueprint $table) {
            $table->string('status', 50)->default('disponible')->change();
        });
    }
};
