<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class CommandeMedicamentsResume
{
    /**
     * @param  iterable<int, object{designation?: string|null, dosage?: string|null}>  $produits
     */
    public static function fromProduits(iterable $produits): string
    {
        $parts = [];
        foreach ($produits as $produit) {
            $designation = trim((string) ($produit->designation ?? ''));
            $dosage = trim((string) ($produit->dosage ?? ''));
            $label = $designation.($dosage !== '' ? ' '.$dosage : '');
            $label = trim($label);
            if ($label !== '') {
                $parts[] = $label;
            }
        }

        return $parts !== [] ? implode(', ', $parts) : '-';
    }

    /**
     * @param  Collection<int, object{designation?: string|null, dosage?: string|null}>  $produits
     */
    public static function fromCollection(Collection $produits): string
    {
        return self::fromProduits($produits);
    }
}
