<?php

namespace App\Services;

use App\Models\GroupeDoublonsProduit;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;

/**
 * Détection et fusion des doublons parmi les produits (médicaments des commandes).
 */
class ProduitDoublonService
{
    public const CRITERES_DISPONIBLES = [
        'designation_similaire' => 'Désignation similaire',
        'dosage_identique' => 'Dosage identique',
        'forme_identique' => 'Forme identique',
    ];

    public function detecterEtCreerGroupes(?array $criteresActifs = null): void
    {
        $criteresActifs = $criteresActifs ?? ['designation_similaire', 'dosage_identique', 'forme_identique'];

        $produits = Produit::all();

        $groupesParCle = [];
        foreach ($produits as $p) {
            $cle = $this->cleGroupage($p, $criteresActifs);
            if ($cle === '') {
                continue;
            }
            $groupesParCle[$cle] = ($groupesParCle[$cle] ?? collect())->push($p);
        }

        foreach ($groupesParCle as $cle => $groupeProduits) {
            if ($groupeProduits->count() < 2) {
                continue;
            }

            $ids = $groupeProduits->pluck('id')->values();

            $existant = GroupeDoublonsProduit::whereHas('produits', fn ($q) => $q->whereIn('produit_id', $ids))
                ->whereIn('statut', ['en_attente', 'verifie'])
                ->first();

            if ($existant) {
                continue;
            }

            $principalId = $this->choisirPrincipalSuggere($groupeProduits);
            $criteresDetectes = $this->criteresDetectes($criteresActifs);

            DB::transaction(function () use ($ids, $principalId, $criteresDetectes) {
                $groupe = GroupeDoublonsProduit::create([
                    'statut' => 'en_attente',
                    'principal_produit_id' => $principalId,
                    'criteres' => $criteresDetectes,
                ]);
                foreach ($ids as $prodId) {
                    $groupe->produits()->attach($prodId, ['is_principal' => $prodId === $principalId]);
                }
            });
        }
    }

    /**
     * Fusionne le groupe : garde le produit principal, transfère commandes et pharmacie, supprime les autres.
     */
    public function fusionner(GroupeDoublonsProduit $groupe, int $principalId): void
    {
        $groupe->load('produits');
        $principal = $groupe->produits->firstWhere('id', $principalId) ?? $groupe->produits->first();
        $secondaires = $groupe->produits->filter(fn ($p) => $p->id !== $principal->id);

        DB::transaction(function () use ($principal, $secondaires, $groupe) {
            foreach ($secondaires as $sec) {
                DB::table('commande_produit')->where('produit_id', $sec->id)->update(['produit_id' => $principal->id]);
                $this->mergeProduitPharmacie($sec->id, $principal->id);
                $sec->delete();
            }
            $groupe->update([
                'statut' => 'fusionne',
                'principal_produit_id' => $principal->id,
            ]);
        });
    }

    private function mergeProduitPharmacie(int $fromProduitId, int $toProduitId): void
    {
        $rows = DB::table('produit_pharmacie')->where('produit_id', $fromProduitId)->get();
        foreach ($rows as $row) {
            $exists = DB::table('produit_pharmacie')
                ->where('produit_id', $toProduitId)
                ->where('pharmacie_id', $row->pharmacie_id)
                ->exists();
            if ($exists) {
                DB::table('produit_pharmacie')
                    ->where('produit_id', $fromProduitId)
                    ->where('pharmacie_id', $row->pharmacie_id)
                    ->delete();
            } else {
                DB::table('produit_pharmacie')
                    ->where('produit_id', $fromProduitId)
                    ->where('pharmacie_id', $row->pharmacie_id)
                    ->update(['produit_id' => $toProduitId]);
            }
        }
    }

    private function cleGroupage(Produit $p, array $criteresActifs): string
    {
        $parties = [];
        if (in_array('designation_similaire', $criteresActifs, true)) {
            $parties[] = $this->normaliser($p->designation ?? '');
        }
        if (in_array('dosage_identique', $criteresActifs, true)) {
            $parties[] = $this->normaliser($p->dosage ?? '');
        }
        if (in_array('forme_identique', $criteresActifs, true)) {
            $parties[] = $this->normaliser($p->forme ?? '');
        }

        return implode('|||', $parties);
    }

    private function normaliser(string $s): string
    {
        $s = trim(mb_strtolower($s, 'UTF-8'));
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = preg_replace('/[\p{P}\p{Z}+]/u', '', $s);
        if (function_exists('normalizer_normalize') && defined('Normalizer::FORM_D')) {
            $normalized = @normalizer_normalize($s, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $s = $normalized;
            }
        }
        $s = preg_replace('/[\p{M}]/u', '', $s);

        return $s !== '' ? $s : '__vide__';
    }

    private function choisirPrincipalSuggere($produits)
    {
        $ventesMap = DB::table('commande_produit')
            ->selectRaw('produit_id, SUM(quantite) as total')
            ->groupBy('produit_id')
            ->pluck('total', 'produit_id')
            ->toArray();

        return $produits
            ->sortByDesc(fn ($p) => [(int) ($ventesMap[$p->id] ?? 0), $p->created_at?->timestamp ?? 0])
            ->first()->id;
    }

    private function criteresDetectes(array $criteresActifs): array
    {
        return array_values(array_intersect($criteresActifs, array_keys(self::CRITERES_DISPONIBLES)))
            ?: ['designation_similaire'];
    }
}
