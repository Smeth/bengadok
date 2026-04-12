<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permet un client avec prénom obligatoire et nom facultatif (création commande).
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('nom', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('nom', 100)->nullable(false)->change();
        });
    }
};
