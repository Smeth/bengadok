<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produit_pharmacie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->foreignId('pharmacie_id')->constrained('pharmacies')->cascadeOnDelete();
            $table->integer('stock')->default(0);
            $table->decimal('prix', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['produit_id', 'pharmacie_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produit_pharmacie');
    }
};
