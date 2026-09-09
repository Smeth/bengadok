<?php

namespace Tests\Unit;

use App\Services\BackupHealthService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupHealthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
        config([
            'backup.backup.destination.disks' => ['backups'],
            'backup.backup.name' => 'BengaDok',
            'filesystems.disks.backups.root' => storage_path('framework/testing/disks/backups'),
        ]);
    }

    public function test_snapshot_is_critical_when_no_archives_exist(): void
    {
        $health = app(BackupHealthService::class)->snapshot();

        $this->assertSame('critical', $health['status']);
        $this->assertContains('Aucune archive disponible sur le serveur.', $health['issues']);
        $this->assertSame(0, $health['archive_count']);
    }

    public function test_snapshot_is_healthy_with_recent_archive(): void
    {
        config([
            'backup.notifications.notifications' => [
                \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            ],
        ]);

        Storage::disk('backups')->put('BengaDok/2026/03/12/recent.zip', 'zip');

        $health = app(BackupHealthService::class)->snapshot();

        $this->assertSame('healthy', $health['status']);
        $this->assertSame(1, $health['archive_count']);
    }

    public function test_notifications_flag_reflects_config(): void
    {
        config(['backup.notifications.notifications' => ['mail' => ['mail']]]);

        $this->assertTrue(app(BackupHealthService::class)->notificationsEnabled());

        config(['backup.notifications.notifications' => []]);

        $this->assertFalse(app(BackupHealthService::class)->notificationsEnabled());
    }
}
