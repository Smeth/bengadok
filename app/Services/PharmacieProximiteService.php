<?php

namespace App\Services;

use App\Models\Pharmacie;
use App\Models\Zone;
use Illuminate\Support\Collection;

class PharmacieProximiteService
{
    /**
     * Trouve les pharmacies ordonnées par proximité selon l'adresse/zone du client.
     * Exclut optionnellement une pharmacie (ex: celle qui a refusé).
     */
    public function trouverPharmaciesProches(string $adresseClient, ?int $exclurePharmacieId = null): Collection
    {
        $adresseLower = mb_strtolower($adresseClient);
        $zones = Zone::all();

        $zoneMatch = $zones->first(function (Zone $zone) use ($adresseLower) {
            return str_contains($adresseLower, mb_strtolower($zone->designation));
        });

        $query = Pharmacie::with('zone', 'typePharmacie');

        if ($zoneMatch) {
            $query->where('zone_id', $zoneMatch->id);
        }

        if ($exclurePharmacieId) {
            $query->where('id', '!=', $exclurePharmacieId);
        }

        $pharmacies = $query->get();

        return $this->ordonnerParProximite($pharmacies, $zoneMatch);
    }

    /**
     * Ordonne les pharmacies par distance (zone d'abord, puis coordonnées si dispo).
     */
    protected function ordonnerParProximite(Collection $pharmacies, ?Zone $zoneClient): Collection
    {
        if ($pharmacies->isEmpty()) {
            return $pharmacies;
        }

        return $pharmacies->sort(function (Pharmacie $a, Pharmacie $b) use ($zoneClient) {
            if ($zoneClient && $zoneClient->latitude && $zoneClient->longitude) {
                $distA = $this->distance(
                    (float) $zoneClient->latitude,
                    (float) $zoneClient->longitude,
                    (float) ($a->latitude ?? 0),
                    (float) ($a->longitude ?? 0)
                );
                $distB = $this->distance(
                    (float) $zoneClient->latitude,
                    (float) $zoneClient->longitude,
                    (float) ($b->latitude ?? 0),
                    (float) ($b->longitude ?? 0)
                );

                return $distA <=> $distB;
            }

            return 0;
        })->values();
    }

    protected function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
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
