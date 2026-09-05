<?php

namespace App\Http\Requests;

use App\Models\Commande;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommandeComplementairesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $commande = $this->route('commande');

        return $commande instanceof Commande
            && $this->user()?->can('updateComplementaires', $commande);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'commentaire' => 'nullable|string',
        ];
    }
}
