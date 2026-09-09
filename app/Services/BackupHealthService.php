<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class BackupHealthService
{
    public function __construct(
        private readonly BackupArchiveService $archives,
    ) {}

    /**
     * @return array{
     *     status: 'healthy'|'warning'|'critical',
     *     issues: list<string>,
     *     archive_count: int,
     *     total_size_bytes: int,
     *     newest_backup_at: string|null,
     *     newest_backup_age_hours: float|null,
     *     disk_path: string,
     *     disk_free_bytes: int|null,
     *     disk_total_bytes: int|null,
     *     disk_used_percent: float|null,
     *     notifications_enabled: bool,
     *     max_age_days: int,
     *     max_storage_mb: int,
     *     scheduled_at: array{clean: string, run: string, monitor: string},
     * }
     */
    public function snapshot(): array
    {
        $archives = $this->archives->listArchives();
        $totalSize = array_sum(array_column($archives, 'size'));
        $newest = $archives[0] ?? null;
        $maxAgeDays = $this->maxAgeDays();
        $maxStorageMb = $this->maxStorageMb();
        $diskPath = $this->backupDiskPath();
        $diskFree = is_string($diskPath) ? @disk_free_space($diskPath) : false;
        $diskTotal = is_string($diskPath) ? @disk_total_space($diskPath) : false;

        $diskFreeBytes = is_numeric($diskFree) ? (int) $diskFree : null;
        $diskTotalBytes = is_numeric($diskTotal) ? (int) $diskTotal : null;
        $diskUsedPercent = ($diskFreeBytes !== null && $diskTotalBytes !== null && $diskTotalBytes > 0)
            ? round((1 - ($diskFreeBytes / $diskTotalBytes)) * 100, 1)
            : null;

        $newestAgeHours = null;
        if (is_array($newest) && isset($newest['date'])) {
            $newestAgeHours = round((time() - strtotime($newest['date'])) / 3600, 1);
        }

        $issues = [];
        $status = 'healthy';

        if ($archives === []) {
            $issues[] = 'Aucune archive disponible sur le serveur.';
            $status = 'critical';
        } elseif ($newestAgeHours !== null && $newestAgeHours > ($maxAgeDays * 24)) {
            $issues[] = sprintf(
                'La dernière sauvegarde date de %.0f h (limite : %d j).',
                $newestAgeHours,
                $maxAgeDays,
            );
            $status = 'critical';
        } elseif ($newestAgeHours !== null && $newestAgeHours > 36) {
            $issues[] = sprintf(
                'La dernière sauvegarde date de %.0f h — vérifiez le cron planifié.',
                $newestAgeHours,
            );
            $status = $this->escalate($status, 'warning');
        }

        $maxStorageBytes = $maxStorageMb * 1024 * 1024;
        if ($totalSize > $maxStorageBytes) {
            $issues[] = sprintf(
                'Les archives occupent %s (limite : %d Mo).',
                $this->formatBytes($totalSize),
                $maxStorageMb,
            );
            $status = 'critical';
        } elseif ($totalSize > (int) ($maxStorageBytes * 0.8)) {
            $issues[] = sprintf(
                'Les archives approchent la limite de stockage (%d Mo).',
                $maxStorageMb,
            );
            $status = $this->escalate($status, 'warning');
        }

        if ($diskFreeBytes !== null && $diskTotalBytes !== null) {
            if ($diskFreeBytes < 1024 * 1024 * 1024) {
                $issues[] = 'Moins de 1 Go d’espace disque libre sur le volume des sauvegardes.';
                $status = 'critical';
            } elseif ($diskUsedPercent !== null && $diskUsedPercent >= 90) {
                $issues[] = sprintf(
                    'Le disque des sauvegardes est utilisé à %.1f %% — libérez de l’espace.',
                    $diskUsedPercent,
                );
                $status = $this->escalate($status, 'warning');
            }
        }

        if (! $this->notificationsEnabled()) {
            $issues[] = 'Les alertes e-mail ne sont pas configurées (BACKUP_NOTIFICATION_MAIL).';
            $status = $this->escalate($status, 'warning');
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'archive_count' => count($archives),
            'total_size_bytes' => $totalSize,
            'newest_backup_at' => is_array($newest) ? ($newest['date'] ?? null) : null,
            'newest_backup_age_hours' => $newestAgeHours,
            'disk_path' => $diskPath ?? '',
            'disk_free_bytes' => $diskFreeBytes,
            'disk_total_bytes' => $diskTotalBytes,
            'disk_used_percent' => $diskUsedPercent,
            'notifications_enabled' => $this->notificationsEnabled(),
            'max_age_days' => $maxAgeDays,
            'max_storage_mb' => $maxStorageMb,
            'scheduled_at' => [
                'clean' => '01:30',
                'run' => '03:00',
                'monitor' => '04:00',
            ],
        ];
    }

    public function maxAgeDays(): int
    {
        return max(1, (int) env('BACKUP_MAX_AGE_DAYS', 2));
    }

    public function maxStorageMb(): int
    {
        return max(100, (int) env('BACKUP_MAX_STORAGE_MB', 5000));
    }

    public function notificationsEnabled(): bool
    {
        $notifications = config('backup.notifications.notifications', []);

        return is_array($notifications) && $notifications !== [];
    }

    private function backupDiskPath(): ?string
    {
        $root = config('filesystems.disks.'.$this->archives->diskName().'.root');

        return is_string($root) ? $root : Storage::disk($this->archives->diskName())->path('');
    }

    /**
     * @param  'healthy'|'warning'|'critical'  $current
     * @return 'healthy'|'warning'|'critical'
     */
    private function escalate(string $current, string $level): string
    {
        $rank = ['healthy' => 0, 'warning' => 1, 'critical' => 2];

        return ($rank[$level] ?? 0) > ($rank[$current] ?? 0) ? $level : $current;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' Ko';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' Mo';
        }

        return round($bytes / (1024 * 1024 * 1024), 2).' Go';
    }
}
