<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Commande extends Model
{
    protected $fillable = [
        'numero', 'client_id', 'pharmacie_id', 'pharmacie_refusee_id', 'ordonnance_id',
        'mode_paiement_id', 'livreur_id', 'montant_livraison_id',
        'date', 'heurs', 'commentaire', 'prix_total',
        'beneficiaire', 'designation', 'status', 'acceptation_client', 'motif_annulation',
    ];

    public const MOTIFS_ANNULATION = [
        'medicaments_indisponibles' => 'Médicaments indisponibles',
        'demande_patient' => 'Demande du patient',
        'erreur_commande' => 'Erreur de commandes',
        'probleme_paiement' => 'Problème de paiement',
    ];

    protected $casts = [
        'date' => 'date',
        'prix_total' => 'decimal:2',
        'acceptation_client' => 'boolean',
    ];

    public const STATUSES = [
        'nouvelle' => 'Nouvelle',
        'en_attente' => 'En attente',
        'validee' => 'Validée',
        'partiellement_validee' => 'Partiellement validée',
        'indisponible_pharmacie' => 'Indisponible (pharmacie)',
        'a_preparer' => 'À préparer',
        'retiree' => 'Retirée',
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

    public function pharmacieRefusee(): BelongsTo
    {
        return $this->belongsTo(Pharmacie::class, 'pharmacie_refusee_id');
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
