<?php

namespace Tests\Feature\Settings;

use App\Services\BackupArchiveService;
use App\Services\BackupRestoreService;
use App\Support\AuthRedirectPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
        config([
            'backup.backup.destination.disks' => ['backups'],
            'backup.backup.name' => 'BengaDok',
        ]);
    }

    public function test_guest_cannot_access_backups_page(): void
    {
        $this->get('/settings/backups')->assertRedirect(route('login'));
    }

    public function test_admin_cannot_access_backups_page(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/settings/backups')
            ->assertRedirect(AuthRedirectPaths::homeForUser($admin));
    }

    public function test_super_admin_can_view_backups_page(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->get('/settings/backups')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Backups')
                ->has('backups')
                ->has('health')
                ->where('backupDisk', 'backups')
                ->where('allowBackupRestore', false));
    }

    public function test_super_admin_can_trigger_backup(): void
    {
        $this->app->forgetInstance(BackupArchiveService::class);

        $this->mock(BackupArchiveService::class, function ($mock): void {
            $mock->shouldReceive('runBackup')->once();
        });

        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->post('/settings/backups')
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_super_admin_can_download_backup_archive(): void
    {
        $service = app(BackupArchiveService::class);
        $path = 'BengaDok/2026/03/12/test-backup.zip';
        Storage::disk('backups')->put($path, 'zip-content');
        $encoded = $service->encodePath($path);

        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->get("/settings/backups/{$encoded}/download")
            ->assertOk()
            ->assertDownload('test-backup.zip');
    }

    public function test_super_admin_can_delete_backup_archive(): void
    {
        $service = app(BackupArchiveService::class);
        $path = 'BengaDok/2026/03/12/remove-me.zip';
        Storage::disk('backups')->put($path, 'zip-content');
        $encoded = $service->encodePath($path);

        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->delete("/settings/backups/{$encoded}")
            ->assertRedirect()
            ->assertSessionHas('status');

        Storage::disk('backups')->assertMissing($path);
    }

    public function test_restore_is_blocked_when_not_allowed(): void
    {
        config(['app.allow_backup_restore' => false]);

        $service = app(BackupArchiveService::class);
        $path = 'BengaDok/2026/03/12/restore.zip';
        Storage::disk('backups')->put($path, 'zip');
        $encoded = $service->encodePath($path);

        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->post("/settings/backups/{$encoded}/restore", [
                'confirmation' => 'RESTAURER SAUVEGARDE',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_super_admin_can_restore_when_allowed(): void
    {
        config(['app.allow_backup_restore' => true]);

        $this->app->forgetInstance(BackupRestoreService::class);

        $this->mock(BackupRestoreService::class, function ($mock): void {
            $mock->shouldReceive('restoreFromEncodedArchive')->once();
        });

        $service = app(BackupArchiveService::class);
        $path = 'BengaDok/2026/03/12/restore.zip';
        Storage::disk('backups')->put($path, 'zip');
        $encoded = $service->encodePath($path);

        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->post("/settings/backups/{$encoded}/restore", [
                'confirmation' => 'RESTAURER SAUVEGARDE',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_restore_requires_confirmation_phrase(): void
    {
        config(['app.allow_backup_restore' => true]);

        $service = app(BackupArchiveService::class);
        $encoded = $service->encodePath('BengaDok/2026/03/12/restore.zip');
        Storage::disk('backups')->put('BengaDok/2026/03/12/restore.zip', 'zip');

        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->post("/settings/backups/{$encoded}/restore", [
                'confirmation' => 'MAUVAISE CONFIRMATION',
            ])
            ->assertSessionHasErrors('confirmation');
    }
}
