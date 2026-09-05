<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Commande;
use App\Models\MontantLivraison;
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
                'date' => now(),
                'heurs' => now()->format('H:i'),
                'commentaire' => $data['commentaire'] ?? null,
                'beneficiaire' => $data['beneficiaire'] ?? null,
                'status' => 'nouvelle',
                'status_pharmacie' => 'nouvelle',
            ]);

            $montants = $this->attachProduits($commande, $data['produits']);
            $liv = 0.0;
            if (! empty($data['montant_livraison_id'])) {
                $liv = (float) (MontantLivraison::query()->find((int) $data['montant_livraison_id'])?->designation ?? 0);
            }
            $commande->update([
                'prix_medicaments' => $montants['prix_medicaments'],
                'prix_parapharma' => $montants['prix_parapharma'],
                'prix_total' => $montants['prix_lignes'] + $liv,
            ]);

            return $commande;
        });
    }

    private function resolveClient(array $data): Client
    {
        if (! empty($data['client_id'])) {
            $client = Client::findOrFail($data['client_id']);
            $attrs = $this->clientAttributesFromPayload($data, onlyPresentKeys: true);

            if ($attrs !== []) {
                $client->update($attrs);
            }

            return $client;
        }

        return Client::create($this->clientAttributesFromPayload($data));
    }

    /**
     * @return array<string, mixed>
     */
    private function clientAttributesFromPayload(array $data, bool $onlyPresentKeys = false): array
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

            $value = $this->trimOrNull($data[$inputKey] ?? null);

            if ($column === 'tel' || $column === 'adresse') {
                if ($value === null && ! $onlyPresentKeys) {
                    $value = '';
                }
                if ($value !== null) {
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

        return $attrs;
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
     * @param  array<int, array{designation: string, dosage?: string|null, forme?: string|null, quantite: int, prix_unitaire: float, type?: string|null}>  $produits
     * @return array{prix_medicaments: float, prix_parapharma: float, prix_lignes: float}
     */
    private function attachProduits(Commande $commande, array $produits): array
    {
        foreach ($produits as $p) {
            $produit = Produit::fromCommandeLine([
                'designation' => $p['designation'],
                'dosage' => $p['dosage'] ?? null,
                'forme' => $p['forme'] ?? null,
                'prix_unitaire' => $p['prix_unitaire'] ?? 0,
                'type' => $p['type'] ?? null,
            ]);
            $quantite = (int) $p['quantite'];
            $prixUnitaire = (float) ($p['prix_unitaire'] ?? 0);
            $commande->produits()->attach($produit->id, [
                'quantite' => $quantite,
                'prix_unitaire' => $prixUnitaire,
                'status' => 'en_attente',
                'type' => $produit->type,
            ]);
        }

        return CommandeMontantCalculator::fromInputLines($produits);
    }

    /**
     * Mise à jour d'une commande « nouvelle » ou « en attente » (édition back-office).
     *
     * @param  array<string, mixed>  $validated
     */
    public function update(Commande $commande, array $validated, ?UploadedFile $ordonnance = null): Commande
    {
        return DB::transaction(function () use ($commande, $validated, $ordonnance) {
            $client = ! empty($validated['client_id'])
                ? Client::findOrFail($validated['client_id'])
                : Client::create([
                    'nom' => $this->trimOrNull($validated['client_nom'] ?? null),
                    'prenom' => $this->trimOrNull($validated['client_prenom'] ?? null),
                    'tel' => $validated['client_tel'],
                    'adresse' => $validated['client_adresse'],
                    'arrondissement' => $validated['client_arrondissement'] ?? null,
                ]);

            if (! empty($validated['client_id'])) {
                $client->update([
                    'nom' => $this->trimOrNull($validated['client_nom'] ?? null),
                    'prenom' => $this->trimOrNull($validated['client_prenom'] ?? null),
                    'tel' => $validated['client_tel'],
                    'adresse' => $validated['client_adresse'],
                    'arrondissement' => $validated['client_arrondissement'] ?? null,
                ]);
            }

            $ordonnanceId = $commande->ordonnance_id;
            if ($ordonnance !== null) {
                $ordonnanceId = Ordonnance::registerNewUpload($ordonnance)->id;
            }

            $commande->update([
                'client_id' => $client->id,
                'pharmacie_id' => $validated['pharmacie_id'],
                'ordonnance_id' => $ordonnanceId,
                'mode_paiement_id' => $validated['mode_paiement_id'] ?? null,
                'commentaire' => $validated['commentaire'] ?? null,
                'beneficiaire' => $validated['beneficiaire'] ?? null,
            ]);

            $this->syncProduitsFromEdition($commande, $validated['produits']);

            $commande->load('produits');
            $montants = CommandeMontantCalculator::fromProduitsRelation($commande->produits);
            $montantLivraison = $commande->montant_livraison_id
                ? (float) (MontantLivraison::find($commande->montant_livraison_id)?->designation ?? 0)
                : 0.0;
            $commande->update([
                'prix_medicaments' => $montants['prix_medicaments'],
                'prix_parapharma' => $montants['prix_parapharma'],
                'prix_total' => $montants['prix_lignes'] + $montantLivraison,
            ]);

            return $commande->fresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $produits
     */
    private function syncProduitsFromEdition(Commande $commande, array $produits): void
    {
        $commande->load('produits');
        $existingByProduitId = $commande->produits->keyBy('id');

        $commande->produits()->detach();
        foreach ($produits as $p) {
            $produit = Produit::fromCommandeLine([
                'designation' => $p['designation'],
                'dosage' => $p['dosage'] ?? null,
                'forme' => $p['forme'] ?? null,
                'prix_unitaire' => $p['prix_unitaire'],
                'type' => $p['type'] ?? null,
            ]);
            $quantite = (int) $p['quantite'];
            $prixUnitaire = (float) $p['prix_unitaire'];
            $existing = isset($p['id']) ? $existingByProduitId->get((int) $p['id']) : null;

            $pivotStatus = $existing?->pivot->status ?? 'en_attente';
            $pivotType = $existing?->pivot->type ?? $produit->type;

            $commande->produits()->attach($produit->id, [
                'quantite' => $quantite,
                'quantite_confirmee' => $existing?->pivot->quantite_confirmee,
                'prix_unitaire' => $prixUnitaire,
                'status' => $pivotStatus,
                'type' => $pivotType,
                'vente_libre' => $existing?->pivot->vente_libre,
            ]);
        }
    }
}
