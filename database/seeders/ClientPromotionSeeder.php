<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Commande;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientPromotionSeeder extends Seeder
{
    /**
     * Clients avec au moins une commande validée / livrée → promu_client_le renseigné.
     * Les autres restent prospects (promu_client_le null).
     */
    public function run(): void
    {
        $promotedAtByClient = Commande::query()
            ->whereIn('status', ['validee', 'retiree', 'livree', 'a_preparer'])
            ->whereNotNull('client_id')
            ->selectRaw('client_id, MIN(COALESCE(validee_admin_at, livree_at, updated_at, created_at)) as promoted_at')
            ->groupBy('client_id')
            ->pluck('promoted_at', 'client_id');

        foreach ($promotedAtByClient as $clientId => $promotedAt) {
            Client::query()
                ->where('id', $clientId)
                ->whereNull('promu_client_le')
                ->update(['promu_client_le' => $promotedAt]);
        }

        Client::query()
            ->whereNotIn('id', $promotedAtByClient->keys()->all())
            ->update(['promu_client_le' => null]);
    }
}
