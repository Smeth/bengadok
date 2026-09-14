<?php

namespace App\Services;

use App\Models\DbCommande;
use Illuminate\Support\Facades\DB;

/**
 * Supprime uniquement les lignes de staging importées et non intégrées.
 * Ne touche jamais aux commandes live, clients, pharmacies ni utilisateurs.
 */
class DbCommandeStagingPurgeService
{
    /**
     * @return array{deleted: int}
     */
    public function purgePendingImports(): array
    {
        return DB::transaction(function (): array {
            $deleted = DbCommande::query()
                ->whereNull('commande_id')
                ->delete();

            return ['deleted' => (int) $deleted];
        });
    }
}
