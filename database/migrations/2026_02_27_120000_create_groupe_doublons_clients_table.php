<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupe_doublons_clients', function (Blueprint $table) {
            $table->id();
            $table->string('statut', 30)->default('en_attente'); // en_attente, verifie, fusionne, ignore
            $table->foreignId('principal_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->json('criteres')->nullable(); // ['nom_identique', 'adresse_identique', 'meme_zone']
            $table->timestamps();
        });

        Schema::create('client_groupe_doublons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('groupe_doublons_id')->constrained('groupe_doublons_clients')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->boolean('is_principal')->default(false);
            $table->timestamps();

            $table->unique(['groupe_doublons_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_groupe_doublons');
        Schema::dropIfExists('groupe_doublons_clients');
    }
};
