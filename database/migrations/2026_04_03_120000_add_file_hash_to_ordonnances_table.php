<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordonnances', function (Blueprint $table) {
            $table->string('file_hash_sha256', 64)->nullable()->after('urlfile')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ordonnances', function (Blueprint $table) {
            $table->dropColumn('file_hash_sha256');
        });
    }
};
