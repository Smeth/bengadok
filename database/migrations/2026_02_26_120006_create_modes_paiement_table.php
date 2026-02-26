<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modes_paiement', function (Blueprint $table) {
            $table->id();
            $table->string('designation', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modes_paiement');
    }
};
