<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('designation');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('pharmacies', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('adresse');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
