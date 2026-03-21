<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class MotifAnnulation extends Model
{
    protected $table = 'motifs_annulation';

    protected $fillable = [
        'slug',
        'label',
        'autorise_relance',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'autorise_relance' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class, 'motif_annulation', 'slug');
    }

    public static function uniqueSlugRule(?int $exceptId = null): Unique
    {
        $rule = Rule::unique('motifs_annulation', 'slug');
        if ($exceptId !== null) {
            $rule->ignore($exceptId);
        }

        return $rule;
    }

    /**
     * @return array<int, array{slug: string, label: string, autorise_relance: bool}>
     */
    public static function orderedForShare(): array
    {
        return static::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['slug', 'label', 'autorise_relance'])
            ->map(fn (self $m) => [
                'slug' => $m->slug,
                'label' => $m->label,
                'autorise_relance' => $m->autorise_relance,
            ])
            ->values()
            ->all();
    }
}
