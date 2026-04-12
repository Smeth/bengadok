<?php

namespace App\Console\Commands;

use App\Services\PharmacyDataResetService;
use Illuminate\Console\Command;

class ResetPharmaciesDataCommand extends Command
{
    protected $signature = 'data:reset-pharmacies {--force : Exécuter sans confirmation}';

    protected $description = 'Supprime toutes les pharmacies, commandes liées, ordonnances associées, et utilisateurs rattachés à une pharmacie (sauf admin/super_admin).';

    public function handle(PharmacyDataResetService $service): int
    {
        if (! PharmacyDataResetService::isAllowed()) {
            $this->error('Réinitialisation refusée : définissez ALLOW_PHARMACY_RESET=true dans .env ou utilisez APP_ENV=local.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Cette opération est irréversible. Continuer ?')) {
                return self::SUCCESS;
            }
        }

        $stats = $service->resetAllPharmacies();

        $this->info('Réinitialisation terminée.');
        $this->table(
            ['Élément', 'Supprimés'],
            [
                ['Commandes', $stats['commandes']],
                ['Ordonnances (fichiers inclus)', $stats['ordonnances']],
                ['Utilisateurs (liés pharmacie)', $stats['users']],
                ['Pharmacies', $stats['pharmacies']],
            ],
        );

        return self::SUCCESS;
    }
}
