<?php

namespace App\Support;

use App\Models\Commande;

class CommandeOrdonnanceFichiers
{
    /**
     * @return list<array{file_url: string|null, is_pdf: bool, label: string}>
     */
    public static function forCommande(Commande $commande): array
    {
        $files = [];

        if ($commande->ordonnance?->file_url) {
            $files[] = [
                'file_url' => $commande->ordonnance->file_url,
                'is_pdf' => (bool) $commande->ordonnance->is_pdf,
                'label' => 'Ordonnance',
            ];
        }

        if ($commande->relationLoaded('piecesJointes')) {
            foreach ($commande->piecesJointes as $pj) {
                if (! $pj->isOrdonnanceKind()) {
                    continue;
                }
                $files[] = [
                    'file_url' => $pj->file_url,
                    'is_pdf' => (bool) $pj->is_pdf,
                    'label' => $pj->label ?: ($pj->original_name ?: 'Ordonnance/article'),
                ];
            }
        }

        return $files;
    }
}
