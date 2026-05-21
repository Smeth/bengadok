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
            $table->timestamp('dispo_pharmacie_at')->nullable()->after('status_pharmacie');
            $table->timestamp('validee_admin_at')->nullable()->after('dispo_pharmacie_at');
            $table->timestamp('livree_at')->nullable()->after('validee_admin_at');
        });

        DB::table('commandes')
            ->whereNotIn('status', ['nouvelle'])
            ->whereNull('dispo_pharmacie_at')
            ->update(['dispo_pharmacie_at' => DB::raw('updated_at')]);

        DB::table('commandes')
            ->whereIn('status', ['validee', 'a_preparer', 'retiree', 'livree'])
            ->whereNull('validee_admin_at')
            ->update(['validee_admin_at' => DB::raw('updated_at')]);

        DB::table('commandes')
            ->whereIn('status', ['retiree', 'livree'])
            ->whereNull('livree_at')
            ->update(['livree_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn(['dispo_pharmacie_at', 'validee_admin_at', 'livree_at']);
        });
    }
};
