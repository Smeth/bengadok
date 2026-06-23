<?php

namespace App\Services;

use App\Models\Commande;
use Carbon\Carbon;

final class CommandeDateFormatter
{
    public function timezone(): string
    {
        return (string) config('app.timezone', 'Africa/Brazzaville');
    }

    /**
     * Extrait une heure HH:MM depuis le champ métier `heurs` (ex. "14:30" ou plage "08:00-19:00").
     */
    public function extractHeureCommande(?string $heursRaw): ?string
    {
        if ($heursRaw === null || trim($heursRaw) === '') {
            return null;
        }
        $s = trim($heursRaw);
        if (preg_match('/^(\d{1,2}:\d{2})$/', $s, $m)) {
            return $m[1];
        }
        if (preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $s, $m)) {
            return $m[1];
        }
        if (preg_match('/(\d{1,2}:\d{2})/', $s, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Affichage cohérent : date métier + heure (fuseau application).
     * Si `heurs` est absent ou illisible, on utilise l'heure de création en base.
     */
    public function formatDateHeure(Commande $commande): string
    {
        $tz = $this->timezone();

        if ($commande->date) {
            $time = $this->extractHeureCommande($commande->heurs ? (string) $commande->heurs : null);
            if ($time === null && $commande->created_at) {
                $time = $commande->created_at->copy()->timezone($tz)->format('H:i');
            }
            if ($time === null) {
                $time = '00:00';
            }
            $datePart = $commande->date instanceof \Carbon\CarbonInterface
                ? $commande->date->format('Y-m-d')
                : Carbon::parse($commande->date)->format('Y-m-d');

            return Carbon::parse($datePart.' '.$time, $tz)->format('d/m/Y H:i');
        }

        if ($commande->created_at) {
            return $commande->created_at->copy()->timezone($tz)->format('d/m/Y H:i');
        }

        return '';
    }

    /** Date seule (reçu, listes sans heure). */
    public function formatDateSeule(Commande $commande): string
    {
        $tz = $this->timezone();

        if ($commande->date) {
            $datePart = $commande->date instanceof \Carbon\CarbonInterface
                ? $commande->date->format('Y-m-d')
                : Carbon::parse($commande->date)->format('Y-m-d');

            return Carbon::parse($datePart, $tz)->format('d/m/Y');
        }

        if ($commande->created_at) {
            return $commande->created_at->copy()->timezone($tz)->format('d/m/Y');
        }

        return '-';
    }
}
