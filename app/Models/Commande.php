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
     * Commandes prises en compte pour l'historique client (hors annulées).
     * Pour le CA, les ventes et les KPI chiffrés, utiliser {@see applyVentesComptabilisees()}.
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
     * Statuts commande admin historiques — préférer {@see applyVentesComptabilisees()} pour le CA et les ventes.
     *
     * @deprecated Utiliser le filtre retrait pharmacie (status_pharmacie = livre).
     */
    public const STATUTS_STATS_VENTES = [
        'validee',
        'a_preparer',
        'retiree',
        'livree',
    ];

    /** Retrait confirmé côté pharmacie — seul stade où le CA et les ventes sont comptabilisés. */
    public const STATUT_PHARMACIE_CA_COMPTABILISE = 'livre';

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Commande>|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyVentesComptabilisees($query, string $alias = 'commandes'): void
    {
        $query
            ->where("{$alias}.status_pharmacie", self::STATUT_PHARMACIE_CA_COMPTABILISE)
            ->where("{$alias}.status", '<>', 'annulee');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Commande>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Commande>
     */
    public function scopeCaComptabilise($query)
    {
        return $query
            ->where('status_pharmacie', self::STATUT_PHARMACIE_CA_COMPTABILISE)
            ->whereNotIn('status', ['annulee']);
    }

    /**
     * Alias sémantique identique à {@see scopeCaComptabilise()} pour les requêtes Eloquent.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Commande>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Commande>
     */
    public function scopeVentesComptabilisees($query)
    {
        return $this->scopeCaComptabilise($query);
    }

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
            ->withPivot('quantite', 'quantite_confirmee', 'prix_unitaire', 'status', 'vente_libre', 'type')
            ->withTimestamps();
    }

    public function piecesJointes(): HasMany
    {
        return $this->hasMany(CommandePieceJointe::class);
    }

    /**
     * Montant des lignes médicaments / parapharmacie (hors livraison).
     *
     * @return array{prix_medicaments: float, prix_parapharma: float, prix_lignes: float}
     */
    public static function computeMontantsFromProduits(
        $produits,
        bool $excludeIndisponible = true,
        bool $excludeEnAttente = true,
    ): array {
        return \App\Services\CommandeMontantCalculator::fromProduitsRelation(
            $produits,
            $excludeIndisponible,
            $excludeEnAttente,
        );
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
