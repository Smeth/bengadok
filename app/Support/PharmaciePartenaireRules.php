<?php

namespace App\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PharmaciePartenaireRules
{
    /**
     * Saisie Gestion commandes (historique) : partenaires et non partenaires.
     */
    public static function allowsNonPartenaireSelection(FormRequest $request): bool
    {
        if ($request->input('_return_hub') !== 'gestion') {
            return false;
        }

        $user = $request->user();

        return $user !== null && $user->hasAnyRole(['admin', 'super_admin']);
    }

    /**
     * @return list<string|Rule>
     */
    public static function partenaireIdRules(): array
    {
        return [
            'required',
            Rule::exists('pharmacies', 'id')->where('est_partenaire', true),
        ];
    }

    /**
     * @return list<string|Rule>
     */
    public static function pharmacieIdRules(FormRequest $request): array
    {
        if (self::allowsNonPartenaireSelection($request)) {
            return ['required', Rule::exists('pharmacies', 'id')];
        }

        return self::partenaireIdRules();
    }
}
