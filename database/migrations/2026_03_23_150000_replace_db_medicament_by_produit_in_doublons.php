<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('db_medicament_groupe_doublons');
        Schema::dropIfExists('groupe_doublons_medicaments');

        Schema::create('groupe_doublons_produits', function (Blueprint $table) {
            $table->id();
            $table->string('statut', 30)->default('en_attente');
            $table->foreignId('principal_produit_id')->nullable()->constrained('produits')->nullOnDelete();
            $table->json('criteres')->nullable();
            $table->timestamps();
        });

        Schema::create('produit_groupe_doublons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('groupe_doublons_id')->constrained('groupe_doublons_produits')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->boolean('is_principal')->default(false);
            $table->timestamps();

            $table->unique(['groupe_doublons_id', 'produit_id'], 'produit_grp_doublons_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produit_groupe_doublons');
        Schema::dropIfExists('groupe_doublons_produits');

        Schema::create('groupe_doublons_medicaments', function (Blueprint $table) {
            $table->id();
            $table->string('statut', 30)->default('en_attente');
            $table->foreignId('principal_db_medicament_id')->nullable()->constrained('db_medicaments')->nullOnDelete();
            $table->json('criteres')->nullable();
            $table->timestamps();
        });

        Schema::create('db_medicament_groupe_doublons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('groupe_doublons_id')->constrained('groupe_doublons_medicaments')->cascadeOnDelete();
            $table->foreignId('db_medicament_id')->constrained('db_medicaments')->cascadeOnDelete();
            $table->boolean('is_principal')->default(false);
            $table->timestamps();
            $table->unique(['groupe_doublons_id', 'db_medicament_id'], 'db_med_grp_doublons_unique');
        });
    }
};
