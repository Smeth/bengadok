<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('db_commandes', function (Blueprint $table) {
            $table->foreignId('commande_id')
                ->nullable()
                ->after('id')
                ->constrained('commandes')
                ->nullOnDelete();
            $table->timestamp('integrated_at')->nullable()->after('empreinte');
            $table->text('integration_error')->nullable()->after('integrated_at');
        });
    }

    public function down(): void
    {
        Schema::table('db_commandes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commande_id');
            $table->dropColumn(['integrated_at', 'integration_error']);
        });
    }
};
