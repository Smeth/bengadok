<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Ordonnance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime toutes les commandes et les ordonnances (fichiers + lignes) qui y étaient liées.
 */
class CommandeOrdonnanceDataPurge
{
    /**
     * @return array{commandes: int, ordonnances: int}
     */
    public static function purgeAll(): array
    {
        $ordonnanceIds = Commande::query()
            ->whereNotNull('ordonnance_id')
            ->pluck('ordonnance_id')
            ->unique()
            ->values()
            ->all();

        DB::table('commandes')->update([
            'parent_id' => null,
            'relance_de_commande_id' => null,
        ]);

        $commandesCount = Commande::query()->count();
        Commande::query()->delete();

        DB::table('ordonnance_verifications')->update([
            'duplicate_of_ordonnance_id' => null,
        ]);

        $ordonnancesCount = 0;
        if ($ordonnanceIds !== []) {
            foreach (Ordonnance::query()->whereIn('id', $ordonnanceIds)->get() as $ordonnance) {
                if ($ordonnance->urlfile) {
                    Storage::disk('public')->delete($ordonnance->urlfile);
                }
            }
            $ordonnancesCount = Ordonnance::query()->whereIn('id', $ordonnanceIds)->delete();
        }

        return [
            'commandes' => $commandesCount,
            'ordonnances' => (int) $ordonnancesCount,
        ];
    }
}
