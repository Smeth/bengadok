<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacieCreditOperation extends Model
{
    public const TYPE_RECHARGE = 'recharge';

    public const TYPE_DEDUCTION = 'deduction';

    protected $fillable = [
        'pharmacie_id',
        'user_id',
        'commande_id',
        'type',
        'credits_delta',
        'cout_xaf',
        'solde_apres',
        'mode_paiement',
        'description',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'credits_delta' => 'integer',
            'cout_xaf' => 'integer',
            'solde_apres' => 'integer',
        ];
    }

    public function pharmacie(): BelongsTo
    {
        return $this->belongsTo(Pharmacie::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
}
