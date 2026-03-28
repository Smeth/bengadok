<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = [
        'delai_relance_meme_pharmacie_heures',
    ];

    protected function casts(): array
    {
        return [
            'delai_relance_meme_pharmacie_heures' => 'integer',
        ];
    }

    public static function delaiRelanceMemePharmacieHeures(): int
    {
        $v = static::query()->value('delai_relance_meme_pharmacie_heures');

        return max(0, (int) ($v ?? 0));
    }
}
