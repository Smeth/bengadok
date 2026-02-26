<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Commande extends Model
{
    protected $fillable = [
        'numero', 'client_id', 'pharmacie_id', 'ordonnance_id',
        'mode_paiement_id', 'livreur_id', 'montant_livraison_id',
        'date', 'heurs', 'commentaire', 'prix_total',
        'beneficiaire', 'designation', 'status',
    ];

    protected $casts = [
        'date' => 'date',
        'prix_total' => 'decimal:2',
    ];

    public const STATUSES = [
        'nouvelle' => 'Nouvelle',
        'en_attente' => 'En attente',
        'validee' => 'Validée',
        'a_preparer' => 'À préparer',
        'livree' => 'Livrée',
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
            ->withPivot('quantite', 'prix_unitaire', 'status')
            ->withTimestamps();
    }
}
