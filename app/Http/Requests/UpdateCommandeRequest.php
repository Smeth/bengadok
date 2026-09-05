<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Commande;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => 'nullable|exists:clients,id',
            'client_nom' => 'nullable|string|max:100',
            'client_prenom' => 'required_without:client_id|string|max:100',
            'client_tel' => 'required_without:client_id|string|max:20',
            'client_adresse' => 'required_without:client_id|string',
            'client_arrondissement' => ['nullable', Rule::in(Client::ARRONDISSEMENTS)],
            'pharmacie_id' => 'required|exists:pharmacies,id',
            'beneficiaire' => 'nullable|string|max:100',
            'produits' => 'required|array|min:1',
            'produits.*.id' => 'nullable|integer|exists:produits,id',
            'produits.*.designation' => 'required|string|max:255',
            'produits.*.dosage' => 'nullable|string|max:50',
            'produits.*.forme' => 'nullable|string|max:50',
            'produits.*.quantite' => 'required|integer|min:1',
            'produits.*.prix_unitaire' => 'required|numeric|min:0',
            'produits.*.type' => 'nullable|string|max:100',
            'ordonnance' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
            'mode_paiement_id' => 'nullable|exists:modes_paiement,id',
            'montant_livraison_id' => 'nullable|exists:montants_livraison,id',
            'commentaire' => 'nullable|string',
        ];
    }
}
