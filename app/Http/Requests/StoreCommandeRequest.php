<?php

namespace App\Http\Requests;

use App\Models\AppSetting;
use App\Models\Commande;
use Illuminate\Contracts\Validation\Validator;
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
        if (is_array($clientNouveau) && ! $this->has('client_id')) {
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->file('ordonnance')) {
                return;
            }
            $raw = $this->input('reutiliser_ordonnance_commande_id');
            if ($raw === null || $raw === '') {
                return;
            }

            $source = Commande::query()->with('client')->find((int) $raw);
            if (! $source) {
                $validator->errors()->add('reutiliser_ordonnance_commande_id', 'Commande source introuvable.');

                return;
            }
            if (! $this->user()?->can('view', $source)) {
                $validator->errors()->add('reutiliser_ordonnance_commande_id', 'Accès refusé à cette commande.');

                return;
            }
            if ($source->status !== 'annulee') {
                $validator->errors()->add('reutiliser_ordonnance_commande_id', 'Seules les commandes annulées peuvent servir de source pour réutiliser une ordonnance.');

                return;
            }
            if (! $source->ordonnance_id) {
                $validator->errors()->add('reutiliser_ordonnance_commande_id', 'Cette commande n’a pas d’ordonnance enregistrée.');

                return;
            }
            if (Commande::query()->where('relance_de_commande_id', $source->id)->exists()) {
                $validator->errors()->add('reutiliser_ordonnance_commande_id', 'Une relance a déjà été créée à partir de cette commande. Si la nouvelle commande est annulée, vous pourrez relancer à partir de celle-ci.');

                return;
            }

            $delaiHeures = AppSetting::delaiRelanceMemePharmacieHeures();
            if ($delaiHeures > 0 && $this->filled('pharmacie_id')) {
                $phId = (int) $this->input('pharmacie_id');
                if ($phId === (int) $source->pharmacie_id && $source->updated_at) {
                    $until = $source->updated_at->copy()->addHours($delaiHeures);
                    if (now()->lt($until)) {
                        $validator->errors()->add(
                            'pharmacie_id',
                            sprintf(
                                'Cette pharmacie ne peut pas être resélectionnée avant le %s (délai de relance : %d h, calculé à partir de la dernière mise à jour de la commande annulée).',
                                $until->timezone(config('app.timezone'))->locale('fr')->isoFormat('LLL'),
                                $delaiHeures
                            )
                        );
                    }
                }
            }

            $reqClientId = $this->input('client_id');
            if ($reqClientId) {
                if ((int) $reqClientId !== (int) $source->client_id) {
                    $validator->errors()->add('reutiliser_ordonnance_commande_id', 'Le client ne correspond pas à la commande source.');
                }

                return;
            }

            $a = $this->normalizeTelDigits($this->input('client_tel'));
            $b = $this->normalizeTelDigits($source->client?->tel);
            if ($a === '' || $a !== $b) {
                $validator->errors()->add('reutiliser_ordonnance_commande_id', 'Le téléphone doit correspondre au client de la commande source.');
            }
        });
    }

    private function normalizeTelDigits(?string $tel): string
    {
        return (string) preg_replace('/\D+/', '', (string) ($tel ?? ''));
    }

    public function rules(): array
    {
        return [
            'client_id' => 'nullable|exists:clients,id',
            'client_nom' => 'required_without:client_id|string|max:100',
            'client_prenom' => 'nullable|string|max:100',
            'client_tel' => 'required_without:client_id|string|max:20',
            'client_adresse' => 'required_without:client_id|string',
            'client_sexe' => 'nullable|in:M,F',
            'pharmacie_id' => 'required|exists:pharmacies,id',
            'beneficiaire' => 'nullable|string|max:100',
            'produits' => 'required|array|min:1',
            'produits.*.designation' => 'required|string|max:255',
            'produits.*.dosage' => 'nullable|string|max:50',
            'produits.*.forme' => 'nullable|string|max:50',
            'produits.*.quantite' => 'required|integer|min:1',
            'produits.*.prix_unitaire' => 'required|numeric|min:0',
            'ordonnance' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
            'reutiliser_ordonnance_commande_id' => 'nullable|integer|exists:commandes,id',
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
