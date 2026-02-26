<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModePaiement extends Model
{
    protected $table = 'modes_paiement';

    protected $fillable = ['designation'];

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }
}
