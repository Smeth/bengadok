<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ordonnance extends Model
{
    protected $fillable = ['urlfile'];

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }
}
