<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Livreur extends Model
{
    protected $fillable = ['nom', 'prenom', 'tel'];

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }
}
