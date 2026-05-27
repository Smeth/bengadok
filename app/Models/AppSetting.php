<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = [
        'delai_relance_meme_pharmacie_heures',
        'parapharma_commission_percent',
        'parapharma_commission_jour_echeance',
        'parapharma_periode_jour_fin',
        'parapharma_credit_seuil_medicament_xaf',
        'parapharma_credit_prix_unitaire_xaf',
        'parapharma_credit_minimum_achat',
        'parapharma_produit_types',
        'parapharma_credit_alerte_seuil',
        'parapharma_credit_deduction_auto',
    ];

    protected function casts(): array
    {
        return [
            'delai_relance_meme_pharmacie_heures' => 'integer',
            'parapharma_commission_percent' => 'float',
            'parapharma_commission_jour_echeance' => 'integer',
            'parapharma_periode_jour_fin' => 'integer',
            'parapharma_credit_seuil_medicament_xaf' => 'integer',
            'parapharma_credit_prix_unitaire_xaf' => 'integer',
            'parapharma_credit_minimum_achat' => 'integer',
            'parapharma_produit_types' => 'array',
            'parapharma_credit_alerte_seuil' => 'integer',
            'parapharma_credit_deduction_auto' => 'boolean',
        ];
    }

    public static function delaiRelanceMemePharmacieHeures(): int
    {
        $v = static::query()->value('delai_relance_meme_pharmacie_heures');

        return max(0, (int) ($v ?? 0));
    }

    /**
     * Paramètres parapharmacie / crédits (priorité : base app_settings, repli config/.env).
     *
     * @return array{
     *     commission_percent: float,
     *     commission_jour_echeance: int,
     *     periode_jour_fin: int,
     *     credit_seuil_medicament_xaf: int,
     *     credit_prix_unitaire_xaf: int,
     *     credit_minimum_achat: int,
     *     produit_types: list<string>
     * }
     */
    public static function parapharmaConfig(): array
    {
        $defaults = static::parapharmaDefaultsFromConfigFile();
        $row = static::query()->first();

        if (! $row) {
            return $defaults;
        }

        $types = $row->parapharma_produit_types;
        if (! is_array($types) || $types === []) {
            $types = $defaults['produit_types'];
        } else {
            $types = array_values(array_filter(array_map(
                static fn ($t) => is_string($t) ? trim($t) : '',
                $types
            )));
        }

        return [
            'commission_percent' => $row->parapharma_commission_percent !== null
                ? (float) $row->parapharma_commission_percent
                : $defaults['commission_percent'],
            'commission_jour_echeance' => $row->parapharma_commission_jour_echeance !== null
                ? (int) $row->parapharma_commission_jour_echeance
                : $defaults['commission_jour_echeance'],
            'periode_jour_fin' => $row->parapharma_periode_jour_fin !== null
                ? (int) $row->parapharma_periode_jour_fin
                : $defaults['periode_jour_fin'],
            'credit_seuil_medicament_xaf' => $row->parapharma_credit_seuil_medicament_xaf !== null
                ? (int) $row->parapharma_credit_seuil_medicament_xaf
                : $defaults['credit_seuil_medicament_xaf'],
            'credit_prix_unitaire_xaf' => $row->parapharma_credit_prix_unitaire_xaf !== null
                ? (int) $row->parapharma_credit_prix_unitaire_xaf
                : $defaults['credit_prix_unitaire_xaf'],
            'credit_minimum_achat' => $row->parapharma_credit_minimum_achat !== null
                ? (int) $row->parapharma_credit_minimum_achat
                : $defaults['credit_minimum_achat'],
            'produit_types' => $types,
            'credit_alerte_seuil' => $row->parapharma_credit_alerte_seuil !== null
                ? (int) $row->parapharma_credit_alerte_seuil
                : $defaults['credit_alerte_seuil'],
            'credit_deduction_auto' => $row->parapharma_credit_deduction_auto !== null
                ? (bool) $row->parapharma_credit_deduction_auto
                : $defaults['credit_deduction_auto'],
        ];
    }

    /**
     * @return array{
     *     commission_percent: float,
     *     commission_jour_echeance: int,
     *     periode_jour_fin: int,
     *     credit_seuil_medicament_xaf: int,
     *     credit_prix_unitaire_xaf: int,
     *     credit_minimum_achat: int,
     *     produit_types: list<string>,
     *     credit_alerte_seuil: int,
     *     credit_deduction_auto: bool
     * }
     */
    public static function parapharmaDefaultsFromConfigFile(): array
    {
        $cfg = config('bengadok.parapharma', []);

        return [
            'commission_percent' => (float) ($cfg['commission_percent'] ?? 1),
            'commission_jour_echeance' => (int) ($cfg['commission_jour_echeance'] ?? $cfg['periode_jour_fin'] ?? 25),
            'periode_jour_fin' => (int) ($cfg['periode_jour_fin'] ?? 25),
            'credit_seuil_medicament_xaf' => (int) ($cfg['credit_seuil_medicament_xaf'] ?? $cfg['credit_seuil_xaf'] ?? 5000),
            'credit_prix_unitaire_xaf' => (int) ($cfg['credit_prix_unitaire_xaf'] ?? $cfg['credit_cout_xaf'] ?? 150),
            'credit_minimum_achat' => (int) ($cfg['credit_minimum_achat'] ?? 10),
            'produit_types' => array_values($cfg['produit_types'] ?? ['Parapharmacie']),
            'credit_alerte_seuil' => (int) ($cfg['credit_alerte_seuil'] ?? 5),
            'credit_deduction_auto' => (bool) ($cfg['credit_deduction_auto'] ?? true),
        ];
    }

    /**
     * Bornes de la période courante (commission, crédits consommés, CA parapharma).
     * Du 1er au jour « fin de période » (paramètre), plafonné à aujourd’hui si mois en cours.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function parapharmaPeriodeBounds(?CarbonInterface $ref = null): array
    {
        $ref = $ref ? Carbon::parse($ref)->startOfMonth() : now()->copy()->startOfMonth();
        $jourFin = static::parapharmaConfig()['periode_jour_fin'];
        $debut = $ref->copy()->startOfMonth();
        $finPeriode = $ref->copy()->day(min($jourFin, $ref->daysInMonth))->endOfDay();
        $now = now();

        if ($ref->isSameMonth($now) && $finPeriode->gt($now)) {
            return [$debut, $now->copy()->endOfDay()];
        }

        return [$debut, $finPeriode];
    }

    public static function ensureRowExists(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        $defaults = static::parapharmaDefaultsFromConfigFile();

        return static::query()->create([
            'delai_relance_meme_pharmacie_heures' => 24,
            'parapharma_commission_percent' => $defaults['commission_percent'],
            'parapharma_commission_jour_echeance' => $defaults['commission_jour_echeance'],
            'parapharma_periode_jour_fin' => $defaults['periode_jour_fin'],
            'parapharma_credit_seuil_medicament_xaf' => $defaults['credit_seuil_medicament_xaf'],
            'parapharma_credit_prix_unitaire_xaf' => $defaults['credit_prix_unitaire_xaf'],
            'parapharma_credit_minimum_achat' => $defaults['credit_minimum_achat'],
            'parapharma_produit_types' => $defaults['produit_types'],
            'parapharma_credit_alerte_seuil' => $defaults['credit_alerte_seuil'],
            'parapharma_credit_deduction_auto' => $defaults['credit_deduction_auto'],
        ]);
    }
}
