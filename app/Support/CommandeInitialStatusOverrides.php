<?php

namespace App\Support;

use App\Models\Commande;
use Carbon\Carbon;

/**
 * Statut, date et heure initiaux pour une commande saisie manuellement ou intégrée depuis l'historique.
 */
class CommandeInitialStatusOverrides
{
    /**
     * @return array<string, mixed>
     */
    public static function fromStatutAndDate(
        string $statut,
        mixed $date = null,
        ?string $heurs = null,
        ?string $numero = null,
    ): array {
        $dateCarbon = self::parseDate($date);
        $heursNormalized = self::normalizeHeurs($heurs);

        $overrides = [
            'date' => $dateCarbon,
            'heurs' => $heursNormalized,
        ];

        if ($numero !== null && $numero !== '') {
            $overrides['numero'] = $numero;
        }

        $statutOverrides = match ($statut) {
            'annulee' => [
                'status' => 'annulee',
                'status_pharmacie' => 'annulee',
            ],
            'retiree' => [
                'status' => 'retiree',
                'status_pharmacie' => Commande::STATUT_PHARMACIE_CA_COMPTABILISE,
                'livree_at' => $dateCarbon,
                'validee_admin_at' => $dateCarbon,
            ],
            'validee' => [
                'status' => 'validee',
                'status_pharmacie' => 'valide_a_preparer',
                'validee_admin_at' => $dateCarbon,
            ],
            'en_attente' => [
                'status' => 'en_attente',
                'status_pharmacie' => 'indisponible',
            ],
            default => [
                'status' => 'nouvelle',
                'status_pharmacie' => 'nouvelle',
            ],
        };

        return array_merge($overrides, $statutOverrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromManualEntry(string $initialStatus, mixed $date, ?string $heurs): array
    {
        $statut = array_key_exists($initialStatus, Commande::STATUSES)
            ? $initialStatus
            : 'nouvelle';

        return self::fromStatutAndDate($statut, $date, $heurs);
    }

    private static function parseDate(mixed $date): Carbon
    {
        if ($date instanceof \DateTimeInterface) {
            return Carbon::instance($date);
        }

        if (is_string($date) && $date !== '') {
            return Carbon::parse($date);
        }

        return Carbon::now();
    }

    private static function normalizeHeurs(?string $heurs): string
    {
        if ($heurs !== null && $heurs !== '') {
            return substr($heurs, 0, 5);
        }

        return now()->format('H:i');
    }
}
