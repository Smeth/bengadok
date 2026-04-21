<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Produit extends Model
{
    protected $fillable = ['pu', 'designation', 'dosage', 'forme', 'type'];

    protected $casts = [
        'pu' => 'decimal:2',
    ];

    public function pharmacies(): BelongsToMany
    {
        return $this->belongsToMany(Pharmacie::class, 'produit_pharmacie')
            ->withPivot('stock', 'prix')
            ->withTimestamps();
    }

    public function commandes(): BelongsToMany
    {
        return $this->belongsToMany(Commande::class, 'commande_produit')
            ->withPivot('quantite', 'prix_unitaire', 'status')
            ->withTimestamps();
    }

    public function getDesignationCompleteAttribute(): string
    {
        $parts = array_filter([$this->designation, $this->dosage, $this->forme]);

        return implode(' ', $parts);
    }

    /**
     * Trouve ou crée le produit catalogue à partir d'une ligne de commande.
     * Met à jour la forme si elle était vide en base (firstOrCreate ne remplit pas à la correspondance).
     *
     * @param  array{designation: string, dosage?: string|null, forme?: string|null, prix_unitaire?: float|int}  $line
     */
    public static function fromCommandeLine(array $line): self
    {
        $designation = trim($line['designation']);
        $dosageRaw = $line['dosage'] ?? null;
        $dosage = is_string($dosageRaw) && trim($dosageRaw) !== '' ? trim($dosageRaw) : null;
        $formeRaw = $line['forme'] ?? null;
        $forme = is_string($formeRaw) && trim($formeRaw) !== '' ? trim($formeRaw) : null;
        $pu = (float) ($line['prix_unitaire'] ?? 0);

        $produit = static::firstOrCreate(
            [
                'designation' => $designation,
                'dosage' => $dosage,
            ],
            [
                'pu' => $pu,
                'forme' => $forme,
                'type' => 'Vente libre',
            ],
        );

        if ($forme !== null && $forme !== '' && trim((string) ($produit->forme ?? '')) === '') {
            $produit->update(['forme' => $forme]);
        }

        return $produit;
    }
}
