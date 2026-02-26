<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    protected $fillable = ['designation'];

    public function pharmacies(): HasMany
    {
        return $this->hasMany(Pharmacie::class);
    }
}
