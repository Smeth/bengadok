<?php

namespace App\Services;

use App\Models\Pharmacie;
use Illuminate\Http\Request;

class PharmacieDashboardContextResolver
{
    /**
     * @return array{
     *     pharmacie_id: int|null,
     *     pharmacies_disponibles: array<int, array{id: int, designation: string}>
     * }
     */
    public function resolve(Request $request): array
    {
        $user = $request->user();
        $userPharmacieId = $user?->pharmacie_id;

        if (! $userPharmacieId) {
            return ['pharmacie_id' => null, 'pharmacies_disponibles' => []];
        }

        $userPharmacie = Pharmacie::query()->find($userPharmacieId);

        $disponibles = Pharmacie::query()
            ->where(function ($q) use ($userPharmacie, $userPharmacieId) {
                $q->where('id', $userPharmacieId);
                if ($userPharmacie?->proprio_email) {
                    $q->orWhere('proprio_email', $userPharmacie->proprio_email);
                }
            })
            ->orderBy('designation')
            ->get(['id', 'designation']);

        $requestedId = $request->integer('pharmacie_id');
        $activeId = $disponibles->contains('id', $requestedId)
            ? $requestedId
            : $userPharmacieId;

        return [
            'pharmacie_id' => $activeId,
            'pharmacies_disponibles' => $disponibles
                ->map(fn (Pharmacie $p) => ['id' => $p->id, 'designation' => $p->designation])
                ->values()
                ->all(),
        ];
    }
}
