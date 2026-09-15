<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commande_pieces_jointes', function (Blueprint $table) {
            $table->string('kind', 32)->default('pharmacie')->after('commande_id');
            $table->index(['commande_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('commande_pieces_jointes', function (Blueprint $table) {
            $table->dropIndex(['commande_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
