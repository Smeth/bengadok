<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commande extends Model
{
    /** Statuts considérés comme commande livrée avec succès (stats dashboard). */
    public const STATUTS_REUSSIS = ['retiree', 'livree'];

    protected $fillable = [
        'numero', 'client_id', 'pharmacie_id', 'parent_id', 'relance_de_commande_id', 'pharmacie_refusee_id', 'ordonnance_id',
        'mode_paiement_id', 'livreur_id', 'montant_livraison_id',
        'date', 'heurs', 'commentaire', 'commentaire_pharmacie', 'prix_total', 'prix_medicaments', 'prix_parapharma',
        'beneficiaire', 'designation', 'status', 'status_pharmacie', 'dispo_pharmacie_at', 'validee_admin_at', 'livree_at',
        'acceptation_client', 'motif_annulation', 'note_annulation',
    ];

    protected $casts = [
        'date' => 'date',
        'prix_total' => 'decimal:2',
        'prix_medicaments' => 'decimal:2',
        'prix_parapharma' => 'decimal:2',
        'acceptation_client' => 'boolean',
        'dispo_pharmacie_at' => 'datetime',
        'validee_admin_at' => 'datetime',
        'livree_at' => 'datetime',
    ];

    // Statuts côté administrateurs
    public const STATUSES = [
        'nouvelle' => 'Nouvelle',
        'en_attente' => 'En attente',
        'validee' => 'Validée',
        'retiree' => 'Livrée',
        'annulee' => 'Annulée',
    ];

    /**
     * Commandes prises en compte pour les KPI client (hors annulées).
     * Inclut les statuts historiques éventuels en base (livree, a_preparer).
     */
    public const STATUTS_COMPTABILISES_CLIENT = [
        'nouvelle',
        'en_attente',
        'validee',
        'retiree',
        'livree',
        'a_preparer',
    ];

    /**
     * Statuts commande pour lesquels les lignes produit comptent dans les stats de ventes (médicaments, CA).
     * Exclut nouvelle / en_attente / annulée : la vente n'est comptée qu'après validation admin.
     */
    public const STATUTS_STATS_VENTES = [
        'validee',
        'a_preparer',
        'retiree',
        'livree',
    ];

    // Statuts côté pharmacie
    public const STATUSES_PHARMACIE = [
        'nouvelle' => 'Nouvelle commande',
        'attente_confirmation' => 'Attente de confirmation',
        'indisponible' => 'Indisponible',
        'valide_a_preparer' => 'Validé - À préparer',
        'livre' => 'Retirée',
        'annulee' => 'Annulée',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function pharmacie(): BelongsTo
    {
        return $this->belongsTo(Pharmacie::class);
    }

    public function pharmacieRefusee(): BelongsTo
    {
        return $this->belongsTo(Pharmacie::class, 'pharmacie_refusee_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(Commande::class, 'parent_id');
    }

    /** Commande annulée dont celle-ci est une relance (réutilisation ordonnance). */
    public function relanceSource(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'relance_de_commande_id');
    }

    /** Relances créées à partir de cette commande (au plus une en pratique). */
    public function relancesDepuis(): HasMany
    {
        return $this->hasMany(Commande::class, 'relance_de_commande_id');
    }

    public function ordonnance(): BelongsTo
    {
        return $this->belongsTo(Ordonnance::class);
    }

    public function modePaiement(): BelongsTo
    {
        return $this->belongsTo(ModePaiement::class);
    }

    public function livreur(): BelongsTo
    {
        return $this->belongsTo(Livreur::class);
    }

    public function montantLivraison(): BelongsTo
    {
        return $this->belongsTo(MontantLivraison::class);
    }

    public function produits(): BelongsToMany
    {
        return $this->belongsToMany(Produit::class, 'commande_produit')
            ->withPivot('quantite', 'quantite_confirmee', 'prix_unitaire', 'status', 'vente_libre')
            ->withTimestamps();
    }

    /**
     * Montant des lignes médicaments / parapharmacie (hors livraison).
     *
     * @return array{prix_medicaments: float, prix_parapharma: float, prix_lignes: float}
     */
    public static function computeMontantsFromProduits($produits, bool $excludeIndisponible = true): array
    {
        return \App\Services\CommandeMontantCalculator::fromProduitsRelation($produits, $excludeIndisponible);
    }

    /**
     * @deprecated Utiliser computeMontantsFromProduits()
     */
    public function computePrixMedicamentsFromProduits(): float
    {
        $this->loadMissing('produits');

        return self::computeMontantsFromProduits($this->produits)['prix_lignes'];
    }

    public function montantLivraisonClient(): float
    {
        $this->loadMissing('montantLivraison');

        return (float) ($this->montantLivraison?->designation ?? 0);
    }

    /**
     * Total client (médicaments + livraison) à partir des lignes et du tarif de livraison.
     */
    public function computePrixTotalClient(): float
    {
        $this->loadMissing('produits');

        return self::computeMontantsFromProduits($this->produits)['prix_lignes'] + $this->montantLivraisonClient();
    }
}
