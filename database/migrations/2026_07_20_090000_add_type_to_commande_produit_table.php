<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commande_produit', function (Blueprint $table) {
            // Instantané du type produit (médicament/parapharma) au moment de la commande :
            // évite qu'un changement ultérieur du type au catalogue (produits.type) ne
            // reclasse rétroactivement le CA/les crédits des commandes déjà passées.
            $table->string('type', 100)->nullable()->after('vente_libre');
        });
    }

    public function down(): void
    {
        Schema::table('commande_produit', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
