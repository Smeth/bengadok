<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base locale des médicaments (DB médicament).
 * Module isolé : non lié aux commandes, au catalogue ou à la recherche produit.
 */
class DbMedicament extends Model
{
    protected $table = 'db_medicaments';

    protected $fillable = [
        'designation',
        'dosage',
        'forme',
        'prix',
        'laboratoire',
        'type',
        'code_article',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'prix' => 'decimal:2',
        ];
    }
}
