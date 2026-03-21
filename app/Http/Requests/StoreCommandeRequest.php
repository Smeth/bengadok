<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() ? $this->user()->can('create', \App\Models\Commande::class) : false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('agent.store-commande')) {
            $this->normalizeAgentInput();
        } elseif ($this->routeIs('commandes.store')) {
            $this->normalizeCommandesInput();
        }
    }

    private function normalizeAgentInput(): void
    {
        $produits = $this->input('produits');
        if (is_string($produits)) {
            $decoded = json_decode($produits, true);
            $this->merge(['produits' => is_array($decoded) ? $decoded : []]);
        }
        $clientNouveau = $this->input('client_nouveau');
        if (is_string($clientNouveau)) {
            $decoded = json_decode($clientNouveau, true);
            $clientNouveau = is_array($decoded) ? $decoded : null;
        }
        if (is_array($clientNouveau) && !$this->has('client_id')) {
            $this->merge([
                'client_nom' => $clientNouveau['nom'] ?? '',
                'client_prenom' => $clientNouveau['prenom'] ?? null,
                'client_tel' => $clientNouveau['tel'] ?? '',
                'client_adresse' => $clientNouveau['adresse'] ?? '',
            ]);
        }
    }

    private function normalizeCommandesInput(): void
    {
        $produits = $this->input('produits');
        if (is_string($produits)) {
            $decoded = json_decode($produits, true);
            $this->merge(['produits' => is_array($decoded) ? $decoded : []]);
        }
    }

    public function rules(): array
    {
        return [
            'client_id' => 'nullable|exists:clients,id',
            'client_nom' => 'required_without:client_id|string|max:100',
            'client_prenom' => 'nullable|string|max:100',
            'client_tel' => 'required_without:client_id|string|max:20',
            'client_adresse' => 'required_without:client_id|string',
            'pharmacie_id' => 'required|exists:pharmacies,id',
            'beneficiaire' => 'nullable|string|max:100',
            'produits' => 'required|array|min:1',
            'produits.*.designation' => 'required|string|max:255',
            'produits.*.dosage' => 'nullable|string|max:50',
            'produits.*.forme' => 'nullable|string|max:50',
            'produits.*.quantite' => 'required|integer|min:1',
            'produits.*.prix_unitaire' => 'required|numeric|min:0',
            'ordonnance' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
            'mode_paiement_id' => 'nullable|exists:modes_paiement,id',
            'montant_livraison_id' => 'nullable|exists:montants_livraison,id',
            'livreur_id' => 'nullable|exists:livreurs,id',
            'commentaire' => 'nullable|string',
        ];
    }

    /**
     * Données normalisées pour CommandeService::create().
     */
    public function getDataForService(): array
    {
        $data = $this->validated();
        if (is_string($data['produits'] ?? null)) {
            $decoded = json_decode($data['produits'], true);
            $data['produits'] = is_array($decoded) ? $decoded : [];
        }
        return $data;
    }
}
