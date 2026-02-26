<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypePharmacie extends Model
{
    protected $fillable = ['designation', 'heurs_id'];

    public function heurs(): BelongsTo
    {
        return $this->belongsTo(Heur::class, 'heurs_id');
    }

    public function pharmacies(): HasMany
    {
        return $this->hasMany(Pharmacie::class);
    }
}
