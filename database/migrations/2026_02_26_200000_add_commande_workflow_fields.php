<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->foreignId('pharmacie_refusee_id')->nullable()->after('pharmacie_id')
                ->constrained('pharmacies')->nullOnDelete();
        });

        Schema::table('commande_produit', function (Blueprint $table) {
            $table->unsignedInteger('quantite_confirmee')->nullable()->after('quantite');
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pharmacie_refusee_id');
        });

        Schema::table('commande_produit', function (Blueprint $table) {
            $table->dropColumn('quantite_confirmee');
        });
    }
};
