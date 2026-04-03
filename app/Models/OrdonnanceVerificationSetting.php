<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdonnanceVerificationSetting extends Model
{
    protected $table = 'ordonnance_verification_settings';

    public const EXECUTION_MODE_QUEUE = 'queue';

    public const EXECUTION_MODE_IMMEDIATE = 'immediate';

    protected $fillable = [
        'enabled',
        'execution_mode',
        'max_prescription_age_days',
        'threshold_pass',
        'threshold_review',
        'rule_weights',
        'keywords_prescriber',
        'keywords_patient',
        'keywords_medicament',
        'block_pass_on_duplicate',
        'tesseract_binary',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'max_prescription_age_days' => 'integer',
            'threshold_pass' => 'integer',
            'threshold_review' => 'integer',
            'rule_weights' => 'array',
            'keywords_prescriber' => 'array',
            'keywords_patient' => 'array',
            'keywords_medicament' => 'array',
            'block_pass_on_duplicate' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrFail();
    }
}
