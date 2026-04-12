<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class CommandesDataResetService
{
    /**
     * @return array{commandes: int, ordonnances: int}
     */
    public function resetAllCommandes(): array
    {
        return DB::transaction(function (): array {
            $purge = CommandeOrdonnanceDataPurge::purgeAll();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $purge;
        });
    }
}
