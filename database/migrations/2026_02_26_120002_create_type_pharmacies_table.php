<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('type_pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('designation', 100);
            $table->foreignId('heurs_id')->nullable()->constrained('heurs')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('type_pharmacies');
    }
};
