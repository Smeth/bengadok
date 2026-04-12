<?php

namespace App\Services;

use App\Models\Client;
use App\Models\GroupeDoublonsClient;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class ClientsDataResetService
{
    /**
     * @return array{commandes: int, ordonnances: int, clients: int, groupes_doublons: int}
     */
    public function resetAllClients(): array
    {
        return DB::transaction(function (): array {
            $purge = CommandeOrdonnanceDataPurge::purgeAll();

            $groupesCount = GroupeDoublonsClient::query()->count();
            DB::table('client_groupe_doublons')->delete();
            GroupeDoublonsClient::query()->delete();

            $clientsCount = Client::query()->count();
            Client::query()->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return [
                'commandes' => $purge['commandes'],
                'ordonnances' => $purge['ordonnances'],
                'clients' => $clientsCount,
                'groupes_doublons' => $groupesCount,
            ];
        });
    }
}
