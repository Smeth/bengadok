<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->string('status_pharmacie', 50)->default('nouvelle')->after('status');
            $table->index('status_pharmacie');
        });

        // Synchroniser les commandes existantes selon leur status admin actuel
        DB::table('commandes')->where('status', 'nouvelle')->update(['status_pharmacie' => 'nouvelle']);
        DB::table('commandes')->where('status', 'en_attente')->update(['status_pharmacie' => 'attente_confirmation']);
        DB::table('commandes')->whereIn('status', ['validee', 'a_preparer'])->update(['status_pharmacie' => 'valide_a_preparer']);
        DB::table('commandes')->where('status', 'indisponible_pharmacie')->update(['status_pharmacie' => 'indisponible']);
        DB::table('commandes')->where('status', 'partiellement_validee')->update(['status_pharmacie' => 'attente_confirmation']);
        DB::table('commandes')->where('status', 'retiree')->update(['status_pharmacie' => 'valide_a_preparer']);
        DB::table('commandes')->where('status', 'livree')->update(['status_pharmacie' => 'valide_a_preparer']);
        DB::table('commandes')->where('status', 'annulee')->update(['status_pharmacie' => 'annulee']);
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropIndex(['status_pharmacie']);
            $table->dropColumn('status_pharmacie');
        });
    }
};
