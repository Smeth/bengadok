<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\MontantLivraison;
use App\Models\Pharmacie;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;

class CommandeReferentielsService
{
    public const CACHE_KEY = 'commandes.referentiels.v2';

    /** TTL court : données peu volatiles, invalidation explicite à la modification. */
    public const TTL_SECONDS = 600;

    /**
     * @return array{
     *     pharmacies: list<array<string, mixed>>,
     *     zones: list<array<string, mixed>>,
     *     montantsLivraison: list<array<string, mixed>>,
     *     modesPaiement: list<array<string, mixed>>,
     *     livreurs: list<array<string, mixed>>,
     *     arrondissements: list<string>,
     *     parapharma_produit_types: list<string>
     * }
     */
    public function all(bool $partenairesUniquement = true): array
    {
        $suffix = $partenairesUniquement ? 'partenaires' : 'toutes';

        return Cache::remember(
            self::CACHE_KEY.'.'.$suffix,
            self::TTL_SECONDS,
            fn (): array => $this->loadFresh($partenairesUniquement),
        );
    }

    public static function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY.'.partenaires');
        Cache::forget(self::CACHE_KEY.'.toutes');
    }

    /**
     * @return array{
     *     pharmacies: list<array<string, mixed>>,
     *     zones: list<array<string, mixed>>,
     *     montantsLivraison: list<array<string, mixed>>,
     *     modesPaiement: list<array<string, mixed>>,
     *     livreurs: list<array<string, mixed>>,
     *     arrondissements: list<string>,
     *     parapharma_produit_types: list<string>
     * }
     */
    private function loadFresh(bool $partenairesUniquement): array
    {
        $pharmaciesQuery = Pharmacie::query()
            ->with(['zone', 'typePharmacie', 'heurs'])
            ->orderBy('designation');

        if ($partenairesUniquement) {
            $pharmaciesQuery->partenaires();
        }

        $zonesQuery = Zone::query()->orderBy('designation');
        if ($partenairesUniquement) {
            $zonesQuery->withCount(['pharmacies as pharmacies_count' => fn ($q) => $q->partenaires()]);
        } else {
            $zonesQuery->withCount('pharmacies');
        }

        return [
            'pharmacies' => $pharmaciesQuery->get()->all(),
            'zones' => $zonesQuery->get()->all(),
            'montantsLivraison' => MontantLivraison::all()->all(),
            'modesPaiement' => ModePaiement::query()->orderBy('designation')->get()->all(),
            'livreurs' => Livreur::orderBy('nom')->orderBy('prenom')->get()->all(),
            'arrondissements' => Client::ARRONDISSEMENTS,
            'parapharma_produit_types' => AppSetting::parapharmaConfig()['produit_types'],
        ];
    }
}
