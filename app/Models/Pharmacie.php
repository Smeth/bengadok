<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pharmacie extends Model
{
    protected $fillable = [
        'zone_id', 'type_pharmacie_id', 'heurs_id',
        'designation', 'telephone', 'adresse', 'email',
        'proprio_nom', 'proprio_tel', 'proprio_email',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function typePharmacie(): BelongsTo
    {
        return $this->belongsTo(TypePharmacie::class);
    }

    public function heurs(): BelongsTo
    {
        return $this->belongsTo(Heur::class, 'heurs_id');
    }

    public function produits(): BelongsToMany
    {
        return $this->belongsToMany(Produit::class, 'produit_pharmacie')
            ->withPivot('stock', 'prix')
            ->withTimestamps();
    }

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
