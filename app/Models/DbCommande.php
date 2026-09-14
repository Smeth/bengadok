<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lignes importées depuis Excel (staging) avant / après intégration
 * dans le système BengaDok (table commandes).
 */
class DbCommande extends Model
{
    public const SOURCE_IMPORT = 'import';

    /** Statuts fichier = mêmes clés/libellés que {@see Commande::STATUSES}. */
    public const STATUTS_FICHIER = Commande::STATUSES;

    /** Phrase de confirmation pour vider le staging import (pas les commandes live). */
    public const PURGE_ALL_CONFIRMATION_PHRASE = 'VIDER IMPORTS STAGING';

    protected $table = 'db_commandes';

    protected $fillable = [
        'ligne_index',
        'code_commande',
        'code_genere',
        'semaine',
        'date_commande',
        'heure_commande',
        'nom_client',
        'sexe',
        'telephone',
        'adresse_livraison',
        'arrondissement',
        'pharmacie',
        'medicaments',
        'quantite',
        'mode_paiement',
        'montant_produits',
        'frais_livraison',
        'total_paye_client',
        'montant_du_theorique',
        'perte',
        'nom_livreur',
        'date_livraison_effective',
        'delai_heures',
        'statut',
        'ca_medicaments',
        'ca_parapharmacie',
        'motif_perte',
        'notes',
        'source',
        'empreinte',
        'commande_id',
        'integrated_at',
        'integration_error',
    ];

    protected function casts(): array
    {
        return [
            'ligne_index' => 'integer',
            'code_genere' => 'boolean',
            'date_commande' => 'date',
            'date_livraison_effective' => 'date',
            'quantite' => 'integer',
            'montant_produits' => 'decimal:2',
            'frais_livraison' => 'decimal:2',
            'total_paye_client' => 'decimal:2',
            'montant_du_theorique' => 'decimal:2',
            'perte' => 'decimal:2',
            'delai_heures' => 'decimal:2',
            'ca_medicaments' => 'decimal:2',
            'ca_parapharmacie' => 'decimal:2',
            'integrated_at' => 'datetime',
        ];
    }

    public function commande(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function isIntegrated(): bool
    {
        return $this->commande_id !== null;
    }

    /**
     * @param  Builder<DbCommande>  $query
     * @return Builder<DbCommande>
     */
    public function scopePendingIntegration(Builder $query): Builder
    {
        return $query->whereNull('commande_id');
    }

    /**
     * @param  Builder<DbCommande>  $query
     * @return Builder<DbCommande>
     */
    public function scopeIntegrated(Builder $query): Builder
    {
        return $query->whereNotNull('commande_id');
    }

    /**
     * @param  Builder<DbCommande>  $query
     * @return Builder<DbCommande>
     */
    public function scopeFailedIntegration(Builder $query): Builder
    {
        return $query->whereNull('commande_id')->whereNotNull('integration_error');
    }

    public function statutFichierLabel(): string
    {
        if ($this->statut === null || $this->statut === '') {
            return 'Non renseigné';
        }

        $key = self::resolveStatutSysteme($this->statut);

        return self::STATUTS_FICHIER[$key] ?? $this->statut;
    }

    /**
     * Parse la colonne « Statut » d'un fichier Excel/CSV vers une clé système.
     */
    public static function parseStatutImportValue(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return null;
        }

        $ascii = strtoupper((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text));
        $ascii = str_replace([' ', '-', '_'], '', $ascii);

        $fromLabel = match (true) {
            str_contains($ascii, 'NOUVEL') => 'nouvelle',
            str_contains($ascii, 'ATTENT') => 'en_attente',
            str_contains($ascii, 'VALID') => 'validee',
            str_contains($ascii, 'LIVR') => 'retiree',
            str_contains($ascii, 'ANNUL') => 'annulee',
            default => null,
        };

        if ($fromLabel !== null) {
            return $fromLabel;
        }

        $normalizedKey = strtolower(str_replace([' ', '-'], '_', $text));
        if (array_key_exists($normalizedKey, Commande::STATUSES)) {
            return $normalizedKey;
        }

        return null;
    }

    /**
     * Clé statut système à appliquer à l'intégration (legacy « livree » → retiree).
     */
    public static function resolveStatutSysteme(?string $statut): string
    {
        if ($statut === null || $statut === '') {
            return 'nouvelle';
        }

        if ($statut === 'livree') {
            return 'retiree';
        }

        if (array_key_exists($statut, Commande::STATUSES)) {
            return $statut;
        }

        return 'nouvelle';
    }

    public static function statutFichierExcelLabel(?string $statut): ?string
    {
        if ($statut === null || $statut === '') {
            return null;
        }

        $key = self::resolveStatutSysteme($statut);

        return self::STATUTS_FICHIER[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function computeEmpreinteFromPayload(array $payload): string
    {
        $parts = [
            mb_strtolower(trim((string) ($payload['code_commande'] ?? ''))),
            (string) ($payload['date_commande'] ?? ''),
            preg_replace('/\s+/', '', (string) ($payload['telephone'] ?? '')) ?? '',
            mb_strtolower(trim((string) ($payload['nom_client'] ?? ''))),
            mb_strtolower(trim((string) ($payload['medicaments'] ?? ''))),
            (string) ($payload['montant_produits'] ?? ''),
            mb_strtolower(trim((string) ($payload['pharmacie'] ?? ''))),
        ];

        return hash('sha256', implode('|', $parts));
    }

    public function refreshEmpreinte(): void
    {
        $this->empreinte = self::computeEmpreinteFromPayload([
            'code_commande' => $this->code_commande,
            'date_commande' => $this->date_commande?->toDateString(),
            'telephone' => $this->telephone,
            'nom_client' => $this->nom_client,
            'medicaments' => $this->medicaments,
            'montant_produits' => $this->montant_produits,
            'pharmacie' => $this->pharmacie,
        ]);
    }

    /**
     * @param  Builder<DbCommande>  $query
     * @return Builder<DbCommande>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('code_commande', 'like', $like)
                ->orWhere('nom_client', 'like', $like)
                ->orWhere('telephone', 'like', $like)
                ->orWhere('medicaments', 'like', $like)
                ->orWhere('pharmacie', 'like', $like)
                ->orWhere('adresse_livraison', 'like', $like)
                ->orWhere('arrondissement', 'like', $like)
                ->orWhere('nom_livreur', 'like', $like);
        });
    }
}
