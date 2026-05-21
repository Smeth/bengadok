<?php

namespace App\Http\Controllers;

use App\Services\MedicamentCatalogueMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedicamentCatalogueMaintenanceController extends Controller
{
    public const CLEAR_CONFIRMATION_PHRASE = 'VIDAGE CATALOGUE';

    public function destroyProduitBulk(Request $request, MedicamentCatalogueMaintenanceService $service): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:produits,id'],
        ]);

        $n = $service->deleteProduitsByIds($validated['ids']);

        $status = match (true) {
            $n === 0 => 'Aucun produit catalogue supprimé.',
            $n === 1 => '1 produit retiré du catalogue.',
            default => sprintf('%d produits retirés du catalogue.', $n),
        };

        return redirect()
            ->route('medicaments.index', $this->medicamentsIndexParams($request, 'catalogue'))
            ->with('status', $status);
    }

    public function clearCatalogue(Request $request, MedicamentCatalogueMaintenanceService $service): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'catalogue_mode' => ['required', Rule::in([
                MedicamentCatalogueMaintenanceService::MODE_PRODUITS_SEULEMENT,
                MedicamentCatalogueMaintenanceService::MODE_COMMANDES_ET_PRODUITS,
            ])],
            'purge_db_medicaments' => ['sometimes', 'boolean'],
            'confirmation' => ['required', Rule::in([self::CLEAR_CONFIRMATION_PHRASE])],
        ]);

        $purgeDb = filter_var($validated['purge_db_medicaments'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($validated['catalogue_mode'] === MedicamentCatalogueMaintenanceService::MODE_PRODUITS_SEULEMENT) {
            $service->clearCataloguePreserveCommandes();
            if ($purgeDb) {
                $service->purgeDbMedicaments();
            }

            return redirect()
                ->route('medicaments.index', $this->medicamentsIndexParams($request, 'catalogue'))
                ->with('status', $this->buildClearStatus(false, $purgeDb));
        }

        $service->clearCatalogueAndCommandes($purgeDb);

        return redirect()
            ->route('medicaments.index', $this->medicamentsIndexParams($request, 'catalogue'))
            ->with('status', $this->buildClearStatus(true, $purgeDb));
    }

    private function authorizeAdmin(Request $request): void
    {
        if (! $request->user()?->hasAnyRole(['admin', 'super_admin'])) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function medicamentsIndexParams(Request $request, string $onglet): array
    {
        return array_filter([
            'search' => $request->input('search'),
            'type' => $request->input('type'),
            'pharmacie_id' => $request->input('pharmacie_id'),
            'tri' => $request->input('tri'),
            'onglet' => $onglet,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function buildClearStatus(bool $withCommandes, bool $purgeDb): string
    {
        $parts = [];

        if ($withCommandes) {
            $parts[] = 'Toutes les commandes ont été supprimées, ainsi que les ordonnances et les fichiers associés.';
            $parts[] = 'Le catalogue produits est vide.';
        } else {
            $parts[] = 'Le catalogue produits a été vidé : les lignes médicaments des commandes ont été retirées automatiquement.';
            $parts[] = 'Les commandes peuvent encore exister mais sans lignes médicaments.';
        }

        if ($purgeDb) {
            $parts[] = 'La base références « DB médicament » a été vidée.';
        }

        return implode(' ', $parts);
    }
}
