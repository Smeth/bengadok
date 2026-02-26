<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
