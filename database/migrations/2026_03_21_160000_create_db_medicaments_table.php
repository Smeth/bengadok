<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('db_medicaments', function (Blueprint $table) {
            $table->id();
            $table->string('designation');
            $table->string('dosage', 120)->nullable();
            $table->string('forme', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_medicaments');
    }
};
