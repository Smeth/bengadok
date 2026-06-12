<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionPeriode extends Model
{
    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_PAYE = 'paye';

    protected $fillable = [
        'pharmacie_id',
        'annee',
        'mois',
        'montant',
        'statut',
        'paye_le',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'paye_le' => 'datetime',
    ];
}
