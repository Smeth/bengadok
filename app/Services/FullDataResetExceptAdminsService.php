<?php

namespace App\Services;

use App\Models\Client;
use App\Models\GroupeDoublonsClient;
use App\Models\GroupeDoublonsProduit;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vide les données métier : commandes, clients, pharmacies, médicaments catalogue, etc.
 * Conserve uniquement les comptes ayant le rôle « admin » ou « super_admin ».
 */
class FullDataResetExceptAdminsService
{
    public function __construct(
        private PharmacyDataResetService $pharmacyDataResetService,
    ) {}

    /**
     * @return array{commandes: int, ordonnances: int, clients: int, pharmacies: int, produits: int, users: int}
     */
    public function reset(): array
    {
        return DB::transaction(function (): array {
            $purge = CommandeOrdonnanceDataPurge::purgeAll();

            DB::table('client_groupe_doublons')->delete();
            GroupeDoublonsClient::query()->delete();
            $clientsCount = Client::query()->count();
            Client::query()->delete();

            $pharma = $this->pharmacyDataResetService->deletePharmacyUsersAndPharmacies();

            DB::table('produit_groupe_doublons')->delete();
            GroupeDoublonsProduit::query()->delete();

            $produitsCount = Produit::query()->count();
            Produit::query()->delete();

            $protectedIds = User::query()
                ->whereHas('roles', function ($q): void {
                    $q->whereIn('name', ['admin', 'super_admin']);
                })
                ->pluck('id')
                ->all();

            $usersToRemove = User::query()
                ->whereNotIn('id', $protectedIds)
                ->pluck('id');

            $usersDeleted = 0;
            foreach ($usersToRemove as $id) {
                $user = User::query()->find($id);
                if ($user === null) {
                    continue;
                }
                $user->syncRoles([]);
                $user->syncPermissions([]);
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->delete();
                $usersDeleted++;
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return [
                'commandes' => $purge['commandes'],
                'ordonnances' => $purge['ordonnances'],
                'clients' => $clientsCount,
                'pharmacies' => $pharma['pharmacies'],
                'produits' => $produitsCount,
                'users' => $usersDeleted,
            ];
        });
    }
}
