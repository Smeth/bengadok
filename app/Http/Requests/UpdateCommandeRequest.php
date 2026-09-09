<?php

namespace App\Http\Requests;

use App\Models\Commande;
use App\Support\CommandeCreationFields;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $commande = $this->route('commande');

        return $commande instanceof Commande
            && $this->user()?->can('update', $commande);
    }

    protected function prepareForValidation(): void
    {
        $produitsInput = $this->input('produits');
        if (is_string($produitsInput)) {
            $produitsDecoded = json_decode($produitsInput, true);
            $this->merge(['produits' => is_array($produitsDecoded) ? $produitsDecoded : []]);
        }

        foreach (['client_nom', 'client_prenom', 'client_arrondissement'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->file('ordonnance')) {
                return;
            }

            if (! CommandeCreationFields::isRequiredForRequest('ordonnance', $this)) {
                return;
            }

            $commande = $this->route('commande');
            if ($commande instanceof Commande && $commande->ordonnance_id) {
                return;
            }

            $validator->errors()->add(
                'ordonnance',
                "L'ordonnance est obligatoire.",
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CommandeCreationFields::updateValidationRules($this);
    }
}
