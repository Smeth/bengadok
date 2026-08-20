<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE clients c
            INNER JOIN zones z ON c.zone_id = z.id
            SET c.arrondissement = z.designation
            WHERE c.arrondissement IS NULL OR TRIM(c.arrondissement) = \'\'
        ');
    }

    public function down(): void
    {
        // Données de rattrapage : pas de rollback automatique.
    }
};
