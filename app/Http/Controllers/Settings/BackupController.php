<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportBackupRequest;
use App\Http\Requests\RestoreBackupRequest;
use App\Services\BackupArchiveService;
use App\Services\BackupHealthService;
use App\Services\BackupRestoreService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(BackupArchiveService $backups, BackupHealthService $health): Response
    {
        return Inertia::render('settings/Backups', [
            'backups' => $backups->listArchives(),
            'backupDisk' => $backups->diskName(),
            'allowBackupRestore' => BackupRestoreService::isAllowed(),
            'health' => $health->snapshot(),
        ]);
    }

    public function store(BackupArchiveService $backups): RedirectResponse
    {
        try {
            $backups->runBackup();
        } catch (\Throwable) {
            return back()->with(
                'error',
                'La sauvegarde a échoué. Vérifiez les logs et que mysqldump est disponible sur le serveur.',
            );
        }

        return back()->with(
            'status',
            'Sauvegarde créée avec succès. Vous pouvez l’exporter depuis la liste ci-dessous.',
        );
    }

    public function download(string $backup, BackupArchiveService $backups): StreamedResponse
    {
        return $backups->download($backup);
    }

    public function destroy(string $backup, BackupArchiveService $backups): RedirectResponse
    {
        try {
            $backups->delete($backup);
        } catch (\Throwable) {
            return back()->with('error', 'Impossible de supprimer cette sauvegarde.');
        }

        return back()->with('status', 'Sauvegarde supprimée.');
    }

    public function restore(
        RestoreBackupRequest $request,
        string $backup,
        BackupRestoreService $restore,
    ): RedirectResponse {
        if (! BackupRestoreService::isAllowed()) {
            return back()->with(
                'error',
                'La restauration n’est pas activée sur cet environnement.',
            );
        }

        try {
            $restore->restoreFromEncodedArchive($backup);
        } catch (\Throwable) {
            return back()->with(
                'error',
                'La restauration a échoué. Vérifiez l’archive, le mot de passe de chiffrement et les logs.',
            );
        }

        return back()->with(
            'status',
            'Restauration terminée. Les données et fichiers ont été remplacés par ceux de l’archive.',
        );
    }

    public function import(
        ImportBackupRequest $request,
        BackupRestoreService $restore,
    ): RedirectResponse {
        if (! BackupRestoreService::isAllowed()) {
            return back()->with(
                'error',
                'La restauration n’est pas activée sur cet environnement.',
            );
        }

        $archive = $request->file('archive');

        if ($archive === null) {
            return back()->with('error', 'Aucun fichier reçu.');
        }

        try {
            $restore->restoreFromUploadedFile($archive);
        } catch (\Throwable) {
            return back()->with(
                'error',
                'L’import a échoué. Vérifiez que le ZIP provient bien d’une sauvegarde BengaDok.',
            );
        }

        return back()->with(
            'status',
            'Archive importée et restaurée avec succès.',
        );
    }
}
