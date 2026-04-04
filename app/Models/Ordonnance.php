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
    protected $fillable = ['urlfile', 'file_hash_sha256'];

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }

    public function verification(): HasOne
    {
        return $this->hasOne(OrdonnanceVerification::class);
    }

    /**
     * Stocke le fichier, calcule l’empreinte SHA-256 et lance l’analyse OCR + règles après commit
     * (uniquement si l’upload est effectué par un compte back-office : admin, super_admin, agent) :
     * file d’attente ({@see OrdonnanceVerificationSetting::EXECUTION_MODE_QUEUE})
     * ou exécution synchrone ({@see OrdonnanceVerificationSetting::EXECUTION_MODE_IMMEDIATE}).
     */
    public static function registerNewUpload(UploadedFile $file): self
    {
        $ext = $file->getClientOriginalExtension();
        $path = $file->storeAs('ordonnances/'.now()->format('Y-m'), uniqid().'.'.$ext, 'public');
        $absolute = Storage::disk('public')->path($path);
        $hash = is_readable($absolute) ? hash_file('sha256', $absolute) : null;

        $ordonnance = self::create([
            'urlfile' => $path,
            'file_hash_sha256' => $hash,
        ]);

        DB::afterCommit(function () use ($ordonnance) {
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
        });

        return $ordonnance;
    }
}
