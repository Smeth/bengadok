<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\DbMedicament;
use App\Models\GroupeDoublonsProduit;
use App\Models\Ordonnance;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class MedicamentCatalogueMaintenanceService
{
    public const MODE_PRODUITS_SEULEMENT = 'produits_seulement';

    public const MODE_COMMANDES_ET_PRODUITS = 'commandes_et_produits';

    public function deleteProduitsByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $deleted = Produit::query()->whereIn('id', $ids)->delete();
        $this->pruneEmptyGroupeDoublons();

        return $deleted;
    }

    public function purgeDbMedicaments(): void
    {
        DbMedicament::query()->delete();
    }

    public function clearCataloguePreserveCommandes(): void
    {
        DB::transaction(function () {
            Produit::query()->delete();
            $this->pruneEmptyGroupeDoublons();
        });
    }

    public function clearCatalogueAndCommandes(bool $purgeDbMedicamentsReference): void
    {
        DB::transaction(function () use ($purgeDbMedicamentsReference) {
            Commande::query()->delete();

            Ordonnance::query()->chunkById(200, function ($ordonnances): void {
                foreach ($ordonnances as $ordonnance) {
                    $this->deleteOrdonnanceFile($ordonnance);
                    $ordonnance->delete();
                }
            });

            Produit::query()->delete();
            $this->pruneEmptyGroupeDoublons();

            if ($purgeDbMedicamentsReference) {
                DbMedicament::query()->delete();
            }
        });
    }

    private function deleteOrdonnanceFile(Ordonnance $ordonnance): void
    {
        $path = $ordonnance->urlfile ?? null;
        if (! is_string($path) || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function pruneEmptyGroupeDoublons(): void
    {
        GroupeDoublonsProduit::query()->whereDoesntHave('produits')->delete();
    }
}
