<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_frequences', function (Blueprint $table) {
            $table->id();
            $table->string('designation');
            $table->string('slug')->unique();
            $table->unsignedInteger('commandes_minimum')->default(0);
            $table->unsignedInteger('commandes_maximum')->nullable();
            /** Moyenne de jours entre deux commandes ≤ cette valeur (client régulier). Laisser vide pour ne pas filtrer sur l’intervalle. */
            $table->unsignedInteger('intervalle_max_jours')->nullable();
            /** Plus la valeur est haute, plus la règle est prioritaire pour l’étiquette affichée sur la fiche. */
            $table->unsignedTinyInteger('priorite')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('client_frequences')->insert([
            [
                'designation' => 'Habitué',
                'slug' => 'habitue',
                'commandes_minimum' => 5,
                'commandes_maximum' => null,
                'intervalle_max_jours' => null,
                'priorite' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'designation' => 'Occasionnel',
                'slug' => 'occasionnel',
                'commandes_minimum' => 0,
                'commandes_maximum' => 4,
                'intervalle_max_jours' => null,
                'priorite' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('client_frequences');
    }
};
