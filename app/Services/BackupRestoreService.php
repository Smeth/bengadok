<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupRestoreService
{
    public function __construct(
        private readonly BackupArchiveService $archives,
    ) {}

    public static function isAllowed(): bool
    {
        if (config('app.allow_backup_restore')) {
            return true;
        }

        return config('app.env') === 'local';
    }

    public function restoreFromEncodedArchive(string $encodedPath): void
    {
        $relativePath = $this->archives->resolvePathForRestore($encodedPath);
        $absolutePath = Storage::disk($this->archives->diskName())->path($relativePath);

        $this->restoreFromArchivePath($absolutePath);
    }

    public function restoreFromUploadedFile(UploadedFile $file): void
    {
        $storedPath = $file->storeAs(
            'backup-restore-uploads',
            uniqid('restore_', true).'.zip',
            'local',
        );

        if (! is_string($storedPath) || $storedPath === '') {
            throw new \RuntimeException('Impossible d’enregistrer le fichier importé.');
        }

        $absolutePath = Storage::disk('local')->path($storedPath);

        try {
            $this->restoreFromArchivePath($absolutePath);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }
    }

    public function restoreFromArchivePath(string $absoluteZipPath): void
    {
        if (! is_file($absoluteZipPath)) {
            throw new \RuntimeException('Archive introuvable.');
        }

        $tempDir = storage_path('app/backup-restore/'.uniqid('restore_', true));

        Artisan::call('down', ['--retry' => 60]);

        try {
            File::ensureDirectoryExists($tempDir);
            $this->extractZip($absoluteZipPath, $tempDir);

            $dumpPath = $this->findDatabaseDump($tempDir);
            if ($dumpPath === null) {
                throw new \RuntimeException('Aucun dump de base de données trouvé dans l’archive.');
            }

            $this->restoreDatabase($dumpPath);
            $this->restoreStorageFiles($tempDir);

            Artisan::call('queue:restart');
        } finally {
            File::deleteDirectory($tempDir);
            Artisan::call('up');
        }
    }

    private function extractZip(string $absoluteZipPath, string $tempDir): void
    {
        $zip = new ZipArchive;
        $result = $zip->open($absoluteZipPath);

        if ($result !== true) {
            throw new \RuntimeException('Impossible d’ouvrir l’archive ZIP.');
        }

        $password = config('backup.backup.password');
        if (is_string($password) && $password !== '') {
            $zip->setPassword($password);
        }

        if (! $zip->extractTo($tempDir)) {
            $zip->close();

            throw new \RuntimeException('Extraction de l’archive échouée (mot de passe incorrect ?).');
        }

        $zip->close();
    }

    private function findDatabaseDump(string $extractRoot): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractRoot, \FilesystemIterator::SKIP_DOTS),
        );

        $candidates = [];

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $pathname = $file->getPathname();

            if (! str_contains(str_replace('\\', '/', $pathname), '/db-dumps/')) {
                continue;
            }

            if (preg_match('/\.sql(\.gz)?$/i', $pathname)) {
                $candidates[] = $pathname;
            }
        }

        if ($candidates === []) {
            return null;
        }

        sort($candidates);

        return $candidates[0];
    }

    private function restoreDatabase(string $dumpPath): void
    {
        Artisan::call('db:wipe', [
            '--force' => true,
            '--drop-views' => true,
            '--drop-types' => true,
        ]);

        $driver = (string) config('database.connections.'.config('database.default').'.driver');

        if ($driver === 'sqlite') {
            $this->importSqliteDump($dumpPath);

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->importMysqlDump($dumpPath);

            return;
        }

        throw new \RuntimeException("Restauration non prise en charge pour le driver « {$driver} ».");
    }

    private function importSqliteDump(string $dumpPath): void
    {
        $sql = $this->readDumpContents($dumpPath);

        DB::connection()->getPdo()->exec($sql);
    }

    private function importMysqlDump(string $dumpPath): void
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! is_array($connection)) {
            throw new \RuntimeException('Configuration base de données invalide.');
        }

        $command = [
            'mysql',
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '3306'),
            '--user='.($connection['username'] ?? 'root'),
            (string) ($connection['database'] ?? ''),
        ];

        $password = $connection['password'] ?? '';
        if (is_string($password) && $password !== '') {
            $command[] = '--password='.$password;
        }

        $process = new Process($command);
        $process->setInput($this->readDumpContents($dumpPath));
        $process->setTimeout(3600);
        $process->mustRun();
    }

    private function readDumpContents(string $dumpPath): string
    {
        if (str_ends_with(strtolower($dumpPath), '.gz')) {
            $contents = gzdecode((string) file_get_contents($dumpPath));

            if ($contents === false) {
                throw new \RuntimeException('Impossible de décompresser le dump SQL.');
            }

            return $contents;
        }

        $contents = file_get_contents($dumpPath);

        if ($contents === false) {
            throw new \RuntimeException('Impossible de lire le dump SQL.');
        }

        return $contents;
    }

    private function restoreStorageFiles(string $extractRoot): void
    {
        $this->restoreStorageSubdir($extractRoot, 'app/private', storage_path('app/private'));
        $this->restoreStorageSubdir($extractRoot, 'app/public', storage_path('app/public'));
    }

    private function restoreStorageSubdir(string $extractRoot, string $relative, string $target): void
    {
        $direct = $extractRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $source = is_dir($direct)
            ? $direct
            : $this->findNestedDirectory($extractRoot, str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if ($source !== null) {
            $this->replaceDirectory($source, $target);
        }
    }

    private function findNestedDirectory(string $root, string $relative): ?string
    {
        $normalizedRelative = str_replace('\\', '/', $relative);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if (! $file->isDir()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $rootPath = str_replace('\\', '/', $root);

            if (str_ends_with($path, $normalizedRelative)) {
                return $file->getPathname();
            }
        }

        $direct = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return is_dir($direct) ? $direct : null;
    }

    private function replaceDirectory(string $source, string $target): void
    {
        File::ensureDirectoryExists($target);

        if (File::isDirectory($target)) {
            File::cleanDirectory($target);
        }

        File::copyDirectory($source, $target);
    }
}
