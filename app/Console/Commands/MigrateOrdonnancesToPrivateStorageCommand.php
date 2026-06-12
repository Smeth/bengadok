<?php

namespace App\Console\Commands;

use App\Models\Ordonnance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateOrdonnancesToPrivateStorageCommand extends Command
{
    protected $signature = 'ordonnances:migrate-to-private {--dry-run : Simuler sans déplacer les fichiers}';

    protected $description = 'Déplace les ordonnances du disque public vers le stockage privé (local).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $skipped = 0;
        $missing = 0;

        Ordonnance::query()
            ->whereNotNull('urlfile')
            ->orderBy('id')
            ->chunkById(100, function ($ordonnances) use ($dryRun, &$moved, &$skipped, &$missing) {
                foreach ($ordonnances as $ordonnance) {
                    $path = $ordonnance->urlfile;
                    if (! is_string($path) || $path === '') {
                        $skipped++;

                        continue;
                    }

                    if (Storage::disk(Ordonnance::STORAGE_DISK)->exists($path)) {
                        $skipped++;

                        continue;
                    }

                    if (! Storage::disk(Ordonnance::LEGACY_DISK)->exists($path)) {
                        $missing++;
                        $this->warn("Fichier introuvable (id {$ordonnance->id}) : {$path}");

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("[dry-run] Déplacerait {$path}");
                        $moved++;

                        continue;
                    }

                    $contents = Storage::disk(Ordonnance::LEGACY_DISK)->get($path);
                    Storage::disk(Ordonnance::STORAGE_DISK)->put($path, $contents);
                    Storage::disk(Ordonnance::LEGACY_DISK)->delete($path);
                    $moved++;
                }
            });

        $this->info("Terminé — déplacés : {$moved}, déjà privés : {$skipped}, introuvables : {$missing}".($dryRun ? ' (simulation)' : ''));

        return self::SUCCESS;
    }
}
