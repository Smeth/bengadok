<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Heur extends Model
{
    protected $table = 'heurs';

    protected $fillable = ['ouverture', 'fermeture'];

    public function typePharmacies(): HasMany
    {
        return $this->hasMany(TypePharmacie::class, 'heurs_id');
    }

    public function pharmacies(): HasMany
    {
        return $this->hasMany(Pharmacie::class, 'heurs_id');
    }
}
