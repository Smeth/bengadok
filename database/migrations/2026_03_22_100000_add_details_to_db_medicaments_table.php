<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('db_medicaments', function (Blueprint $table) {
            $table->decimal('prix', 12, 2)->nullable()->after('forme');
            $table->string('laboratoire', 255)->nullable()->after('prix');
            $table->string('type', 80)->nullable()->after('laboratoire');
            $table->string('code_article', 120)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('db_medicaments', function (Blueprint $table) {
            $table->dropColumn(['prix', 'laboratoire', 'type', 'code_article']);
        });
    }
};
