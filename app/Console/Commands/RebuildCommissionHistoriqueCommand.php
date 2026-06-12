<?php

namespace App\Console\Commands;

use App\Services\CommissionHistoriqueService;
use Illuminate\Console\Command;

class RebuildCommissionHistoriqueCommand extends Command
{
    protected $signature = 'commissions:rebuild-historique
                            {--months=24 : Nombre de mois à recalculer en arrière}
                            {--keep-paid : Conserver les périodes déjà marquées payées}
                            {--dry-run : Simuler sans modifier la base}';

    protected $description = 'Recalcule les commissions parapharma en base depuis le CA réel et corrige les statuts fictifs.';

    public function handle(CommissionHistoriqueService $service): int
    {
        $months = max(1, (int) $this->option('months'));
        $resetPayees = ! (bool) $this->option('keep-paid');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Reconstruction historique commissions (%d mois, reset payés: %s%s)',
            $months,
            $resetPayees ? 'oui' : 'non',
            $dryRun ? ', simulation' : '',
        ));

        $stats = $service->reconstruireHistorique($months, $resetPayees, $dryRun);

        $this->table(
            ['Action', 'Nombre'],
            collect($stats)->map(fn ($count, $action) => [$action, $count])->values()->all(),
        );

        if ($dryRun) {
            $this->warn('Simulation terminée — relancez sans --dry-run pour appliquer.');
        } else {
            $this->info('Historique commissions synchronisé avec le CA réel.');
        }

        return self::SUCCESS;
    }
}
