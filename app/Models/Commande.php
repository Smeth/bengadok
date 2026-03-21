<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commande extends Model
{
    protected $fillable = [
        'numero', 'client_id', 'pharmacie_id', 'parent_id', 'pharmacie_refusee_id', 'ordonnance_id',
        'mode_paiement_id', 'livreur_id', 'montant_livraison_id',
        'date', 'heurs', 'commentaire', 'prix_total',
        'beneficiaire', 'designation', 'status', 'status_pharmacie', 'acceptation_client', 'motif_annulation', 'note_annulation',
    ];

    protected $casts = [
        'date' => 'date',
        'prix_total' => 'decimal:2',
        'acceptation_client' => 'boolean',
    ];

    // Statuts côté administrateurs
    public const STATUSES = [
        'nouvelle' => 'Nouvelle',
        'en_attente' => 'En attente',
        'validee' => 'Validée',
        'retiree' => 'Livrée',
        'annulee' => 'Annulée',
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
            ->withPivot('quantite', 'quantite_confirmee', 'prix_unitaire', 'status')
            ->withTimestamps();
    }
}
