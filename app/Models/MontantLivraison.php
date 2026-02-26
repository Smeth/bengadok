<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MontantLivraison extends Model
{
    protected $table = 'montants_livraison';

    protected $fillable = ['designation'];

    protected $casts = [
        'designation' => 'decimal:2',
    ];

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }
}
