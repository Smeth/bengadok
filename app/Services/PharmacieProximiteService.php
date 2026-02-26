<?php

namespace App\Services;

use App\Models\Pharmacie;
use App\Models\Zone;
use Illuminate\Support\Collection;

class PharmacieProximiteService
{
    /**
     * Trouve les pharmacies les plus proches du client selon l'adresse/zone.
     * Pour l'instant : recherche par zone (si adresse contient le nom de zone)
     * ou liste toutes les pharmacies par zone.
     */
    public function trouverPharmaciesProches(string $adresseClient): Collection
    {
        $adresseLower = mb_strtolower($adresseClient);
        $zones = Zone::all();

        $zoneMatch = $zones->first(function (Zone $zone) use ($adresseLower) {
            return str_contains($adresseLower, mb_strtolower($zone->designation));
        });

        if ($zoneMatch) {
            return Pharmacie::where('zone_id', $zoneMatch->id)
                ->with('zone', 'typePharmacie')
                ->get();
        }

        return Pharmacie::with('zone', 'typePharmacie')->get();
    }

    /**
     * Pharmacies d'une zone donnée.
     */
    public function pharmaciesParZone(int $zoneId): Collection
    {
        return Pharmacie::where('zone_id', $zoneId)
            ->with('zone', 'typePharmacie')
            ->get();
    }
}
