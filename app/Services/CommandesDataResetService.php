<?php

namespace App\Services;

use App\Models\GroupeDoublonsProduit;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class CommandesDataResetService
{
    /**
     * @return array{commandes: int, ordonnances: int}|array{commandes: int, ordonnances: int, produits_catalogue: int}
     */
    public function resetAllCommandes(bool $alsoPurgeMedicamentsCatalogue = false): array
    {
        return DB::transaction(function () use ($alsoPurgeMedicamentsCatalogue): array {
            $purge = CommandeOrdonnanceDataPurge::purgeAll();
            $produitsCount = 0;

            if ($alsoPurgeMedicamentsCatalogue) {
                DB::table('produit_groupe_doublons')->delete();
                GroupeDoublonsProduit::query()->delete();
                $produitsCount = Produit::query()->count();
                Produit::query()->delete();
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            if ($alsoPurgeMedicamentsCatalogue) {
                return [
                    'commandes' => $purge['commandes'],
                    'ordonnances' => $purge['ordonnances'],
                    'produits_catalogue' => $produitsCount,
                ];
            }

            return $purge;
        });
    }
}
