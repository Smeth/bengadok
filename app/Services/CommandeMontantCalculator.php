<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Produit;

final class CommandeMontantCalculator
{
    public static function isParapharmaType(?string $type): bool
    {
        $types = AppSetting::parapharmaConfig()['produit_types'];

        if ($types === []) {
            return is_string($type) && stripos($type, 'parapharm') !== false;
        }

        return in_array($type, $types, true);
    }

    /**
     * @param  iterable<int, Produit>  $produits
     * @return array{prix_medicaments: float, prix_parapharma: float, prix_lignes: float}
     */
    public static function fromProduitsRelation(iterable $produits, bool $excludeIndisponible = true): array
    {
        $med = 0.0;
        $para = 0.0;

        foreach ($produits as $produit) {
            $pivot = $produit->pivot;
            if ($excludeIndisponible && ($pivot->status ?? '') === 'indisponible') {
                continue;
            }

            $qte = $pivot->quantite_confirmee ?? $pivot->quantite;
            $montant = (float) $qte * (float) $pivot->prix_unitaire;

            if (self::isParapharmaType($produit->type)) {
                $para += $montant;
            } else {
                $med += $montant;
            }
        }

        return [
            'prix_medicaments' => $med,
            'prix_parapharma' => $para,
            'prix_lignes' => $med + $para,
        ];
    }

    /**
     * @param  array<int, array{type?: string|null, quantite: int, prix_unitaire: float|int|string}>  $lines
     * @return array{prix_medicaments: float, prix_parapharma: float, prix_lignes: float}
     */
    public static function fromInputLines(array $lines): array
    {
        $med = 0.0;
        $para = 0.0;

        foreach ($lines as $line) {
            $montant = (float) ($line['prix_unitaire'] ?? 0) * (int) ($line['quantite'] ?? 0);
            $type = is_string($line['type'] ?? null) ? trim($line['type']) : '';

            if (self::isParapharmaType($type !== '' ? $type : null)) {
                $para += $montant;
            } else {
                $med += $montant;
            }
        }

        return [
            'prix_medicaments' => $med,
            'prix_parapharma' => $para,
            'prix_lignes' => $med + $para,
        ];
    }
}
