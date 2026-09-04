<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommandeCreationFields
{
    /** @var array<string, array{label: string, default: bool, group: string, contexts: list<string>}> */
    public const META = [
        'client_nom' => [
            'label' => 'Nom du client',
            'default' => false,
            'group' => 'client',
            'contexts' => ['admin', 'agent'],
        ],
        'client_prenom' => [
            'label' => 'Prénom du client',
            'default' => true,
            'group' => 'client',
            'contexts' => ['admin', 'agent'],
        ],
        'client_tel' => [
            'label' => 'Téléphone',
            'default' => true,
            'group' => 'client',
            'contexts' => ['admin', 'agent'],
        ],
        'client_adresse' => [
            'label' => 'Adresse',
            'default' => true,
            'group' => 'client',
            'contexts' => ['admin', 'agent'],
        ],
        'client_arrondissement' => [
            'label' => 'Arrondissement',
            'default' => true,
            'group' => 'client',
            'contexts' => ['admin', 'agent'],
        ],
        'client_sexe' => [
            'label' => 'Civilité (sexe)',
            'default' => false,
            'group' => 'client',
            'contexts' => ['admin', 'agent'],
        ],
        'beneficiaire' => [
            'label' => 'Bénéficiaire',
            'default' => false,
            'group' => 'commande',
            'contexts' => ['admin'],
        ],
        'date' => [
            'label' => 'Date de commande',
            'default' => false,
            'group' => 'commande',
            'contexts' => ['admin', 'agent'],
        ],
        'heurs' => [
            'label' => 'Heure',
            'default' => false,
            'group' => 'commande',
            'contexts' => ['admin', 'agent'],
        ],
        'ordonnance' => [
            'label' => 'Ordonnance (fichier)',
            'default' => false,
            'group' => 'commande',
            'contexts' => ['admin', 'agent'],
        ],
        'mode_paiement_id' => [
            'label' => 'Mode de paiement',
            'default' => false,
            'group' => 'commande',
            'contexts' => ['agent'],
        ],
        'montant_livraison_id' => [
            'label' => 'Montant de livraison',
            'default' => false,
            'group' => 'commande',
            'contexts' => ['admin', 'agent'],
        ],
        'livreur_id' => [
            'label' => 'Livreur',
            'default' => false,
            'group' => 'commande',
            'contexts' => ['agent'],
        ],
        'commentaire' => [
            'label' => 'Commentaire',
            'default' => false,
            'group' => 'commande',
            'contexts' => ['admin', 'agent'],
        ],
    ];

    /**
     * @return array<string, bool>
     */
    public static function config(): array
    {
        return AppSetting::commandeCreationFieldsConfig();
    }

    public static function isRequired(string $field): bool
    {
        $config = self::config();

        return (bool) ($config[$field] ?? self::META[$field]['default'] ?? false);
    }

    public static function isRequiredForRequest(string $field, FormRequest $request): bool
    {
        if (! self::isRequired($field)) {
            return false;
        }

        return self::fieldAppliesOnRoute($field, $request);
    }

    /**
     * @return list<array{key: string, label: string, group: string, required: bool, default: bool, contexts: list<string>}>
     */
    public static function definitionsForFrontend(): array
    {
        $config = self::config();

        return collect(self::META)
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'group' => $meta['group'],
                'required' => (bool) ($config[$key] ?? $meta['default']),
                'default' => (bool) $meta['default'],
                'contexts' => $meta['contexts'],
            ])
            ->values()
            ->all();
    }

    /**
     * Règles de validation création commande (admin, agent, relance).
     *
     * @return array<string, mixed>
     */
    public static function validationRules(FormRequest $request): array
    {
        $sansClientExistant = ! $request->filled('client_id');

        $stringRequired = static fn (string $field): array => self::isRequiredForRequest($field, $request) && $sansClientExistant
            ? ['required', 'string']
            : ['nullable', 'string'];

        $stringRequiredAnyClient = static fn (string $field): array => self::isRequiredForRequest($field, $request)
            ? ['required', 'string']
            : ['nullable', 'string'];

        $idRequired = static fn (string $field): string => self::isRequiredForRequest($field, $request)
            ? 'required|exists:'.self::existsTableFor($field)
            : 'nullable|exists:'.self::existsTableFor($field);

        return [
            'client_id' => 'nullable|exists:clients,id',
            'client_nom' => array_merge($stringRequired('client_nom'), ['max:100']),
            'client_prenom' => self::isRequiredForRequest('client_prenom', $request) && $sansClientExistant
                ? ['required', 'string', 'max:100']
                : ['nullable', 'string', 'max:100'],
            'client_tel' => self::isRequiredForRequest('client_tel', $request) && $sansClientExistant
                ? ['required', 'string', 'max:20']
                : ['nullable', 'string', 'max:20'],
            'client_adresse' => self::isRequiredForRequest('client_adresse', $request) && $sansClientExistant
                ? ['required', 'string']
                : ['nullable', 'string'],
            'client_arrondissement' => self::isRequiredForRequest('client_arrondissement', $request) && $sansClientExistant
                ? ['required', 'string', Rule::in(Client::ARRONDISSEMENTS)]
                : ['nullable', 'string', Rule::in(Client::ARRONDISSEMENTS)],
            'client_sexe' => self::isRequiredForRequest('client_sexe', $request) && $sansClientExistant
                ? ['required', 'in:M,F']
                : ['nullable', 'in:M,F'],
            'pharmacie_id' => 'required|exists:pharmacies,id',
            'beneficiaire' => array_merge($stringRequiredAnyClient('beneficiaire'), ['max:100']),
            'date' => self::isRequiredForRequest('date', $request)
                ? 'required|date'
                : 'nullable|date',
            'heurs' => self::isRequiredForRequest('heurs', $request)
                ? 'required|string|max:10'
                : 'nullable|string|max:10',
            'produits' => 'required|array|min:1',
            'produits.*.designation' => 'required|string|max:255',
            'produits.*.dosage' => 'nullable|string|max:50',
            'produits.*.forme' => 'nullable|string|max:50',
            'produits.*.quantite' => 'required|integer|min:1',
            'produits.*.prix_unitaire' => 'required|numeric|min:0',
            'produits.*.type' => 'nullable|string|max:100',
            'ordonnance' => self::isRequiredForRequest('ordonnance', $request)
                ? 'required_without:reutiliser_ordonnance_commande_id|nullable|file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240'
                : 'nullable|file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
            'reutiliser_ordonnance_commande_id' => 'nullable|integer|exists:commandes,id',
            'mode_paiement_id' => $idRequired('mode_paiement_id'),
            'montant_livraison_id' => $idRequired('montant_livraison_id'),
            'livreur_id' => $idRequired('livreur_id'),
            'commentaire' => self::isRequiredForRequest('commentaire', $request)
                ? 'required|string'
                : 'nullable|string',
        ];
    }

    private static function fieldAppliesOnRoute(string $field, FormRequest $request): bool
    {
        $contexts = self::META[$field]['contexts'] ?? [];

        if ($contexts === []) {
            return false;
        }

        if ($request->routeIs('agent.store-commande')) {
            return in_array('agent', $contexts, true);
        }

        if ($request->routeIs('commandes.store')) {
            return in_array('admin', $contexts, true);
        }

        return true;
    }

    private static function existsTableFor(string $field): string
    {
        return match ($field) {
            'mode_paiement_id' => 'modes_paiement,id',
            'montant_livraison_id' => 'montants_livraison,id',
            'livreur_id' => 'livreurs,id',
            default => 'id',
        };
    }

    /**
     * @param  array<string, bool>  $input
     * @return array<string, bool>
     */
    public static function normalizeInput(array $input): array
    {
        $normalized = [];

        foreach (self::META as $key => $meta) {
            $normalized[$key] = array_key_exists($key, $input)
                ? (bool) $input[$key]
                : (bool) $meta['default'];
        }

        return $normalized;
    }
}
