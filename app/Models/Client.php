<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    public const NICHE_LABELS = [
        'parent' => 'Parent',
        'personnes_agees' => 'Personnes âgées',
        'maladie_chronique' => 'Maladie chronique',
        'aidant_familial' => 'Aidant familial',
    ];

    public const CANAL_LABELS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'bouche_a_oreille' => 'Bouche à oreille',
        'autres' => 'Autres',
    ];

    /** Libellés courts pour le profil (catégories / tiers fréquents), clés = valeur stockée sur commande.beneficiaire */
    public const BENEFICIAIRE_SHORT_LABELS = [
        'Soi-même' => 'Soi',
        'Sa mère' => 'Mère',
        'Son père' => 'Père',
        'Son enfant' => 'Enfants',
        'Autre' => 'Autre',
    ];

    protected $fillable = [
        'nom', 'prenom', 'tel', 'tel_secondaire', 'adresse', 'sexe', 'email', 'zone_id', 'client_depuis',
        'niches', 'canal_acquisition',
    ];

    protected $casts = [
        'client_depuis' => 'date',
        'niches' => 'array',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }
}
