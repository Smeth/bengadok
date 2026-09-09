<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupArchiveService
{
    /**
     * @return list<array{id: string, filename: string, path: string, size: int, date: string}>
     */
    public function listArchives(): array
    {
        $disk = Storage::disk($this->diskName());
        $prefix = $this->backupFolderName();

        if (! $disk->exists($prefix)) {
            return [];
        }

        $archives = [];

        foreach ($disk->allFiles($prefix) as $path) {
            if (! str_ends_with(strtolower($path), '.zip')) {
                continue;
            }

            $archives[] = [
                'id' => $this->encodePath($path),
                'filename' => basename($path),
                'path' => $path,
                'size' => $disk->size($path),
                'date' => date('c', $disk->lastModified($path)),
            ];
        }

        usort(
            $archives,
            static fn (array $left, array $right): int => strcmp($right['date'], $left['date']),
        );

        return $archives;
    }

    public function runBackup(bool $onlyDb = false, bool $onlyFiles = false): void
    {
        $options = ['--disable-notifications' => true];

        if ($onlyDb) {
            $options['--only-db'] = true;
        }

        if ($onlyFiles) {
            $options['--only-files'] = true;
        }

        $exitCode = Artisan::call('backup:run', $options);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'La commande backup:run a échoué.');
        }
    }

    public function delete(string $encodedPath): void
    {
        $path = $this->resolveEncodedPath($encodedPath);
        Storage::disk($this->diskName())->delete($path);
    }

    public function resolvePathForRestore(string $encodedPath): string
    {
        return $this->resolveEncodedPath($encodedPath);
    }

    public function download(string $encodedPath): StreamedResponse
    {
        $path = $this->resolveEncodedPath($encodedPath);
        $disk = Storage::disk($this->diskName());

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->download($path, basename($path));
    }

    public function encodePath(string $path): string
    {
        return rtrim(strtr(base64_encode($path), '+/', '-_'), '=');
    }

    public function diskName(): string
    {
        $disks = config('backup.backup.destination.disks', ['backups']);

        return is_string($disks[0] ?? null) ? $disks[0] : 'backups';
    }

    public function backupFolderName(): string
    {
        return (string) config('backup.backup.name', 'BengaDok');
    }

    private function resolveEncodedPath(string $encodedPath): string
    {
        $padding = strlen($encodedPath) % 4;
        $normalized = strtr($encodedPath, '-_', '+/').str_repeat('=', $padding === 0 ? 0 : 4 - $padding);
        $path = base64_decode($normalized, true);

        if (! is_string($path) || $path === '') {
            abort(404);
        }

        if (str_contains($path, '..')) {
            abort(404);
        }

        $prefix = $this->backupFolderName();
        if ($path !== $prefix && ! str_starts_with($path, $prefix.'/')) {
            abort(404);
        }

        if (! Storage::disk($this->diskName())->exists($path)) {
            abort(404);
        }

        return $path;
    }
}
