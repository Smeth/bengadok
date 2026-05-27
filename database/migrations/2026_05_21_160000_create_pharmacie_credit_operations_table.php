<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacie_credit_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacie_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commande_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->integer('credits_delta');
            $table->unsignedInteger('cout_xaf')->default(0);
            $table->unsignedInteger('solde_apres');
            $table->string('mode_paiement', 80)->nullable();
            $table->string('description');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['pharmacie_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacie_credit_operations');
    }
};
