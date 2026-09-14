<?php

namespace App\Support;

/**
 * Normalise les champs client issus des formulaires commande / imports.
 * Convertit les chaînes vides (souvent null après middleware Laravel) en valeurs persistables.
 */
final class ClientPayloadNormalizer
{
    /** Colonnes string NOT NULL en base — null interdit, chaîne vide acceptée. */
    public const NOT_NULL_STRING_FIELDS = ['tel', 'adresse'];

    public static function trimOrNull(?string $value): ?string
    {
        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    public static function coerceNotNullStrings(array $attrs): array
    {
        foreach (self::NOT_NULL_STRING_FIELDS as $field) {
            if (array_key_exists($field, $attrs) && $attrs[$field] === null) {
                $attrs[$field] = '';
            }
        }

        return $attrs;
    }

    /**
     * Attributs Eloquent à partir d'un payload commande (client_nom, client_tel, …).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributesFromCommandePayload(array $data, bool $onlyPresentKeys = false): array
    {
        $map = [
            'client_nom' => 'nom',
            'client_prenom' => 'prenom',
            'client_tel' => 'tel',
            'client_adresse' => 'adresse',
            'client_arrondissement' => 'arrondissement',
        ];

        $attrs = [];

        foreach ($map as $inputKey => $column) {
            if ($onlyPresentKeys && ! array_key_exists($inputKey, $data)) {
                continue;
            }

            $value = self::trimOrNull(isset($data[$inputKey]) ? (string) $data[$inputKey] : null);

            if (in_array($column, self::NOT_NULL_STRING_FIELDS, true)) {
                $keyPresent = array_key_exists($inputKey, $data);
                if ($value === null && ($keyPresent || ! $onlyPresentKeys)) {
                    $attrs[$column] = '';
                } elseif ($value !== null) {
                    $attrs[$column] = $value;
                }

                continue;
            }

            if ($value !== null || ! $onlyPresentKeys) {
                $attrs[$column] = $value;
            }
        }

        if (! $onlyPresentKeys || array_key_exists('client_sexe', $data)) {
            $attrs['sexe'] = ! empty($data['client_sexe']) ? $data['client_sexe'] : null;
        }

        return self::coerceNotNullStrings($attrs);
    }
}
