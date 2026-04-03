<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordonnance_verification_settings', function (Blueprint $table) {
            $table->string('execution_mode', 32)->default('queue')->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('ordonnance_verification_settings', function (Blueprint $table) {
            $table->dropColumn('execution_mode');
        });
    }
};
