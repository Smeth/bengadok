<?php

use App\Models\Commande;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('promu_client_le')->nullable()->after('client_depuis');
        });

        $rows = DB::table('commandes')
            ->whereIn('status', Commande::STATUTS_STATS_VENTES)
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->selectRaw(
                'client_id, MIN(COALESCE(validee_admin_at, updated_at, created_at)) as promoted_at'
            )
            ->get();

        foreach ($rows as $row) {
            DB::table('clients')
                ->where('id', $row->client_id)
                ->update(['promu_client_le' => $row->promoted_at]);
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('promu_client_le');
        });
    }
};
