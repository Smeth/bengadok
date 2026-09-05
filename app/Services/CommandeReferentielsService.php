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
    public const CACHE_KEY = 'commandes.referentiels.v1';

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
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, fn (): array => $this->loadFresh());
    }

    public static function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
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
    private function loadFresh(): array
    {
        return [
            'pharmacies' => Pharmacie::with(['zone', 'typePharmacie', 'heurs'])->get()->all(),
            'zones' => Zone::withCount('pharmacies')->get()->all(),
            'montantsLivraison' => MontantLivraison::all()->all(),
            'modesPaiement' => ModePaiement::query()->orderBy('designation')->get()->all(),
            'livreurs' => Livreur::orderBy('nom')->orderBy('prenom')->get()->all(),
            'arrondissements' => Client::ARRONDISSEMENTS,
            'parapharma_produit_types' => AppSetting::parapharmaConfig()['produit_types'],
        ];
    }
}
