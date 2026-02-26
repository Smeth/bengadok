<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('type_pharmacie_id')->nullable()->constrained('type_pharmacies')->nullOnDelete();
            $table->foreignId('heurs_id')->nullable()->constrained('heurs')->nullOnDelete();
            $table->string('designation', 200);
            $table->string('telephone', 20);
            $table->string('adresse');
            $table->string('email')->nullable();
            $table->string('proprio_nom', 100)->nullable();
            $table->string('proprio_tel', 20)->nullable();
            $table->string('proprio_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacies');
    }
};
