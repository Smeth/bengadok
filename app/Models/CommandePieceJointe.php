<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CommandePieceJointe extends Model
{
    public const STORAGE_DISK = 'local';

    protected $table = 'commande_pieces_jointes';

    protected $fillable = [
        'commande_id',
        'uploaded_by',
        'urlfile',
        'original_name',
        'mime_type',
        'size_bytes',
        'label',
    ];

    protected $hidden = [
        'urlfile',
    ];

    protected $appends = [
        'file_url',
        'is_image',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (! is_string($this->urlfile) || $this->urlfile === '') {
            return null;
        }

        return route('commande-pieces-jointes.fichier', $this);
    }

    public function getIsImageAttribute(): bool
    {
        if (is_string($this->mime_type) && str_starts_with($this->mime_type, 'image/')) {
            return true;
        }

        if (! is_string($this->urlfile) || $this->urlfile === '') {
            return false;
        }

        return (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $this->urlfile);
    }

    /** @deprecated Toujours false pour les nouvelles pièces pharmacie (images uniquement). */
    public function getIsPdfAttribute(): bool
    {
        return false;
    }

    public function resolveStorageDisk(): string
    {
        return Ordonnance::storageDiskForPath($this->urlfile);
    }

    public function deleteStoredFile(): void
    {
        if (! is_string($this->urlfile) || $this->urlfile === '') {
            return;
        }

        $path = $this->urlfile;
        $disk = $this->resolveStorageDisk();

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    public static function storeFromUpload(
        UploadedFile $file,
        Commande $commande,
        ?User $user,
        ?string $label = null,
    ): self {
        $mime = $file->getMimeType() ?? '';
        if (! str_starts_with($mime, 'image/')) {
            throw new \InvalidArgumentException('Seules les images sont acceptées.');
        }

        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg';
        $path = $file->storeAs(
            'commandes-pieces-jointes/'.now()->format('Y-m'),
            uniqid('pj_', true).'.'.$ext,
            self::STORAGE_DISK,
        );

        if ($path === false || ! is_string($path) || $path === '') {
            throw new \RuntimeException('Impossible d\'enregistrer la pièce jointe.');
        }

        return self::query()->create([
            'commande_id' => $commande->id,
            'uploaded_by' => $user?->id,
            'urlfile' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'label' => $label !== null && trim($label) !== '' ? trim($label) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'original_name' => $this->original_name,
            'file_url' => $this->file_url,
            'is_image' => $this->is_image,
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
            'uploaded_by' => $this->uploadedBy?->name,
        ];
    }
}
