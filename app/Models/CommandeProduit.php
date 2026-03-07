<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandeProduit extends Model
{
    protected $table = 'commande_produit';

    protected $fillable = ['commande_id', 'produit_id', 'quantite', 'quantite_confirmee', 'prix_unitaire', 'status'];

    protected $casts = [
        'prix_unitaire' => 'decimal:2',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->prix_unitaire * $this->quantite;
    }
}
