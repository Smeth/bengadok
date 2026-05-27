<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_periodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->decimal('montant', 12, 2)->default(0);
            $table->string('statut', 20)->default('en_cours');
            $table->timestamp('paye_le')->nullable();
            $table->timestamps();

            $table->unique(['annee', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_periodes');
    }
};
