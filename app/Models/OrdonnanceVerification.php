<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdonnanceVerification extends Model
{
    protected $fillable = [
        'ordonnance_id',
        'status',
        'ocr_text',
        'parsed_prescription_date',
        'score',
        'decision',
        'rule_results',
        'flags',
        'duplicate_of_ordonnance_id',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'parsed_prescription_date' => 'date',
            'rule_results' => 'array',
            'flags' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function ordonnance(): BelongsTo
    {
        return $this->belongsTo(Ordonnance::class);
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Ordonnance::class, 'duplicate_of_ordonnance_id');
    }
}
