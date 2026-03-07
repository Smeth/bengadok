<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AppResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset {--force : Confirmer la réinitialisation sans demande}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Réinitialise l\'application (migrate:fresh --seed)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('Êtes-vous sûr de vouloir réinitialiser l\'application ? Toutes les données seront supprimées.')) {
                $this->info('Opération annulée.');

                return self::SUCCESS;
            }
        }

        $this->call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);

        $this->info('Application réinitialisée avec succès.');

        return self::SUCCESS;
    }
}
