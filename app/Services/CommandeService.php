<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Ordonnance;
use App\Models\Produit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CommandeService
{
    /**
     * Données normalisées pour créer une commande.
     * client_id OU (client_nom, client_prenom, client_tel, client_adresse)
     */
    public function create(array $data, ?UploadedFile $ordonnance = null): Commande
    {
        return DB::transaction(function () use ($data, $ordonnance) {
            $client = $this->resolveClient($data);
            $ordonnanceId = $this->resolveOrdonnanceId($data, $ordonnance);

            $reuseId = $data['reutiliser_ordonnance_commande_id'] ?? null;
            $reuseId = ($reuseId === null || $reuseId === '') ? null : (int) $reuseId;

            $commande = Commande::create([
                'numero' => 'BDK'.now()->format('ymdHis').rand(100, 999),
                'client_id' => $client->id,
                'pharmacie_id' => $data['pharmacie_id'],
                'ordonnance_id' => $ordonnanceId,
                'relance_de_commande_id' => $reuseId,
                'mode_paiement_id' => $data['mode_paiement_id'] ?? null,
                'montant_livraison_id' => $data['montant_livraison_id'] ?? null,
                'livreur_id' => $data['livreur_id'] ?? null,
                'date' => $data['date'] ?? now(),
                'heurs' => $data['heurs'] ?? now()->format('H:i'),
                'commentaire' => $data['commentaire'] ?? null,
                'beneficiaire' => $data['beneficiaire'] ?? null,
                'status' => 'nouvelle',
                'status_pharmacie' => 'nouvelle',
            ]);

            $prixTotal = $this->attachProduits($commande, $data['produits']);
            $commande->update(['prix_total' => $prixTotal]);

            return $commande;
        });
    }

    private function resolveClient(array $data): Client
    {
        if (! empty($data['client_id'])) {
            $client = Client::findOrFail($data['client_id']);
            if (isset($data['client_nom'])) {
                $attrs = [
                    'nom' => $data['client_nom'],
                    'prenom' => $this->trimOrNull($data['client_prenom'] ?? null),
                    'tel' => $data['client_tel'],
                    'adresse' => $data['client_adresse'],
                ];
                if (array_key_exists('client_sexe', $data)) {
                    $attrs['sexe'] = $data['client_sexe'] ?: null;
                }
                $client->update($attrs);
            } elseif (array_key_exists('client_sexe', $data)) {
                $client->update(['sexe' => $data['client_sexe'] ?: null]);
            }

            return $client;
        }

        return Client::create([
            'nom' => $data['client_nom'],
            'prenom' => $this->trimOrNull($data['client_prenom'] ?? null),
            'tel' => $data['client_tel'],
            'adresse' => $data['client_adresse'],
            'sexe' => ! empty($data['client_sexe']) ? $data['client_sexe'] : null,
        ]);
    }

    private function trimOrNull(?string $value): ?string
    {
        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }

    private function resolveOrdonnanceId(array $data, ?UploadedFile $file): ?int
    {
        $fromUpload = $this->storeOrdonnance($file);
        if ($fromUpload !== null) {
            return $fromUpload;
        }

        $reuseCommandeId = $data['reutiliser_ordonnance_commande_id'] ?? null;
        if ($reuseCommandeId === null || $reuseCommandeId === '') {
            return null;
        }

        $source = Commande::query()->find((int) $reuseCommandeId);

        return $source?->ordonnance_id;
    }

    private function storeOrdonnance(?UploadedFile $file): ?int
    {
        if (! $file) {
            return null;
        }

        return Ordonnance::registerNewUpload($file)->id;
    }

    /**
     * @param  array<int, array{designation: string, dosage?: string, forme?: string, quantite: int, prix_unitaire: float}>  $produits
     */
    private function attachProduits(Commande $commande, array $produits): float
    {
        $prixTotal = 0;
        foreach ($produits as $p) {
            $forme = $this->trimOrNull($p['forme'] ?? null);
            $produit = Produit::firstOrCreate(
                [
                    'designation' => trim($p['designation']),
                    'dosage' => $this->trimOrNull($p['dosage'] ?? null),
                ],
                [
                    'pu' => (float) ($p['prix_unitaire'] ?? 0),
                    'forme' => $forme,
                    'type' => 'Vente libre',
                ]
            );
            $quantite = (int) $p['quantite'];
            $prixUnitaire = (float) ($p['prix_unitaire'] ?? 0);
            $commande->produits()->attach($produit->id, [
                'quantite' => $quantite,
                'prix_unitaire' => $prixUnitaire,
                'status' => 'disponible',
            ]);
            $prixTotal += $prixUnitaire * $quantite;
        }

        return $prixTotal;
    }
}
