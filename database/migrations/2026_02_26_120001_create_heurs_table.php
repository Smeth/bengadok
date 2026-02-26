<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heurs', function (Blueprint $table) {
            $table->id();
            $table->string('ouverture', 10);
            $table->string('fermeture', 10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heurs');
    }
};
