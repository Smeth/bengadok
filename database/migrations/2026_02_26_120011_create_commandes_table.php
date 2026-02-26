<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('pharmacie_id')->constrained('pharmacies')->cascadeOnDelete();
            $table->foreignId('ordonnance_id')->nullable()->constrained('ordonnances')->nullOnDelete();
            $table->foreignId('mode_paiement_id')->nullable()->constrained('modes_paiement')->nullOnDelete();
            $table->foreignId('livreur_id')->nullable()->constrained('livreurs')->nullOnDelete();
            $table->foreignId('montant_livraison_id')->nullable()->constrained('montants_livraison')->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('heurs', 10)->nullable();
            $table->string('commentaire')->nullable();
            $table->decimal('prix_total', 12, 2)->default(0);
            $table->string('beneficiaire', 100)->nullable();
            $table->string('designation', 255)->nullable();
            $table->string('status', 50)->default('nouvelle');
            $table->timestamps();

            $table->index('status');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
