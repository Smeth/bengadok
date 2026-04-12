<?php

namespace App\Services;

use App\Models\Pharmacie;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class PharmacyDataResetService
{
    /**
     * Supprime toutes les pharmacies, les commandes associées, les ordonnances liées,
     * et tous les utilisateurs ayant un pharmacie_id (sauf rôles admin / super_admin).
     *
     * @return array{commandes: int, ordonnances: int, users: int, pharmacies: int}
     */
    public function resetAllPharmacies(): array
    {
        return DB::transaction(function (): array {
            $purge = CommandeOrdonnanceDataPurge::purgeAll();
            $pharma = $this->deletePharmacyUsersAndPharmacies();

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return [
                'commandes' => $purge['commandes'],
                'ordonnances' => $purge['ordonnances'],
                'users' => $pharma['users'],
                'pharmacies' => $pharma['pharmacies'],
            ];
        });
    }

    /**
     * Supprime les utilisateurs liés à une pharmacie (sauf admin / super_admin) puis toutes les pharmacies.
     * À appeler après une purge des commandes si besoin.
     *
     * @return array{users: int, pharmacies: int}
     */
    public function deletePharmacyUsersAndPharmacies(): array
    {
        $userIds = User::query()
            ->whereNotNull('pharmacie_id')
            ->whereDoesntHave('roles', function ($q): void {
                $q->whereIn('name', ['admin', 'super_admin']);
            })
            ->pluck('id');

        $usersCount = $userIds->count();

        foreach ($userIds as $id) {
            $user = User::query()->find($id);
            if ($user === null) {
                continue;
            }
            $user->syncRoles([]);
            $user->syncPermissions([]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        }

        $pharmaciesCount = Pharmacie::query()->count();
        Pharmacie::query()->delete();

        return [
            'users' => $usersCount,
            'pharmacies' => $pharmaciesCount,
        ];
    }

    public static function isAllowed(): bool
    {
        if (config('app.allow_pharmacy_reset')) {
            return true;
        }

        return config('app.env') === 'local';
    }
}
