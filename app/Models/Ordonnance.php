<?php

namespace App\Models;

use App\Jobs\ProcessOrdonnanceVerificationJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Ordonnance extends Model
{
    public const STORAGE_DISK = 'local';

    public const LEGACY_DISK = 'public';

    protected $fillable = ['urlfile', 'file_hash_sha256'];

    protected $hidden = [
        'urlfile',
    ];

    protected $appends = [
        'file_url',
        'is_pdf',
    ];

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }

    public function verification(): HasOne
    {
        return $this->hasOne(OrdonnanceVerification::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        if (! is_string($this->urlfile) || $this->urlfile === '') {
            return null;
        }

        return route('ordonnances.fichier', $this);
    }

    public function getIsPdfAttribute(): bool
    {
        if (! is_string($this->urlfile) || $this->urlfile === '') {
            return false;
        }

        return str_ends_with(strtolower($this->urlfile), '.pdf');
    }

    public static function storageDiskForPath(?string $path): string
    {
        if (! is_string($path) || $path === '') {
            return self::STORAGE_DISK;
        }

        if (Storage::disk(self::STORAGE_DISK)->exists($path)) {
            return self::STORAGE_DISK;
        }

        if (Storage::disk(self::LEGACY_DISK)->exists($path)) {
            return self::LEGACY_DISK;
        }

        return self::STORAGE_DISK;
    }

    public function resolveStorageDisk(): string
    {
        return self::storageDiskForPath($this->urlfile);
    }

    public function absolutePath(): ?string
    {
        if (! is_string($this->urlfile) || $this->urlfile === '') {
            return null;
        }

        return Storage::disk($this->resolveStorageDisk())->path($this->urlfile);
    }

    public function deleteStoredFile(): void
    {
        if (! is_string($this->urlfile) || $this->urlfile === '') {
            return;
        }

        $path = $this->urlfile;

        if (Storage::disk(self::STORAGE_DISK)->exists($path)) {
            Storage::disk(self::STORAGE_DISK)->delete($path);
        }

        if (Storage::disk(self::LEGACY_DISK)->exists($path)) {
            Storage::disk(self::LEGACY_DISK)->delete($path);
        }
    }

    /**
     * Stocke le fichier, calcule l’empreinte SHA-256 et lance l’analyse OCR + règles après commit
     * (uniquement si l’upload est effectué par un compte back-office : admin, super_admin, agent) :
     * file d’attente ({@see OrdonnanceVerificationSetting::EXECUTION_MODE_QUEUE})
     * ou exécution synchrone ({@see OrdonnanceVerificationSetting::EXECUTION_MODE_IMMEDIATE}).
     */
    public static function registerNewUpload(UploadedFile $file): self
    {
        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $path = $file->storeAs(
            'ordonnances/'.now()->format('Y-m'),
            uniqid('ord_', true).'.'.$ext,
            self::STORAGE_DISK,
        );

        if ($path === false || ! is_string($path) || $path === '') {
            throw new \RuntimeException('Impossible d\'enregistrer le fichier ordonnance (vérifiez les droits sur storage/app/private).');
        }

        $absolute = Storage::disk(self::STORAGE_DISK)->path($path);
        $hash = is_readable($absolute) ? hash_file('sha256', $absolute) : null;

        $ordonnance = self::create([
            'urlfile' => $path,
            'file_hash_sha256' => $hash,
        ]);

        DB::afterCommit(function () use ($ordonnance) {
            try {
                $user = Auth::user();
                if ($user === null || ! $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])) {
                    return;
                }

                OrdonnanceVerification::query()->firstOrCreate(
                    ['ordonnance_id' => $ordonnance->id],
                    ['status' => 'pending', 'decision' => 'pending']
                );

                $settings = OrdonnanceVerificationSetting::query()->first();
                $immediate = $settings
                    && $settings->execution_mode === OrdonnanceVerificationSetting::EXECUTION_MODE_IMMEDIATE;

                if ($immediate) {
                    ProcessOrdonnanceVerificationJob::dispatchSync($ordonnance->id);
                } else {
                    ProcessOrdonnanceVerificationJob::dispatch($ordonnance->id);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Vérification ordonnance ignorée après upload (commande enregistrée).', [
                    'ordonnance_id' => $ordonnance->id,
                    'message' => $e->getMessage(),
                ]);
            }
        });

        return $ordonnance;
    }
}
