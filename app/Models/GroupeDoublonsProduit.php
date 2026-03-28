<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Groupe de doublons parmi les produits (médicaments des commandes).
 */
class GroupeDoublonsProduit extends Model
{
    protected $table = 'groupe_doublons_produits';

    protected $fillable = [
        'statut',
        'principal_produit_id',
        'criteres',
    ];

    protected $casts = [
        'criteres' => 'array',
    ];

    public function principalProduit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'principal_produit_id');
    }

    public function produits(): BelongsToMany
    {
        return $this->belongsToMany(Produit::class, 'produit_groupe_doublons', 'groupe_doublons_id', 'produit_id')
            ->withPivot('is_principal')
            ->withTimestamps();
    }
}
