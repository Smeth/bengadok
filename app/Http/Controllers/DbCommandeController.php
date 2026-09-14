<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\DbCommande;
use App\Services\CommandeAdminService;
use App\Services\CommandeExportService;
use App\Services\CommandeIndexService;
use App\Services\CommandeReferentielsService;
use App\Services\DbCommandeImportService;
use App\Services\DbCommandeIntegrationService;
use App\Services\DbCommandeStagingPurgeService;
use App\Support\PaginatesSafely;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;

/**
 * Module Gestion commandes : liste globale, saisie manuelle, import Excel, export et intégration.
 */
class DbCommandeController extends Controller
{
    public function __construct(
        private DbCommandeImportService $importService,
        private DbCommandeIntegrationService $integrationService,
        private CommandeReferentielsService $referentielsService,
        private CommandeIndexService $commandeIndexService,
        private CommandeExportService $commandeExportService,
        private CommandeAdminService $commandeAdminService,
        private DbCommandeStagingPurgeService $stagingPurgeService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorizeView($request);

        $user = $request->user();
        abort_unless($user, 403);

        $tab = (string) $request->input('tab', 'commandes');
        if ($tab === 'importer') {
            $tab = 'imports';
        }
        if (! in_array($tab, ['commandes', 'imports'], true)) {
            $tab = 'commandes';
        }

        $isImportAdmin = $user->hasAnyRole(['admin', 'super_admin']);
        if ($tab === 'imports' && ! $isImportAdmin) {
            $tab = 'commandes';
        }

        $referentiels = $this->referentielsService->all(partenairesUniquement: false);
        $importStats = [
            'total' => DbCommande::query()->count(),
            'pending' => DbCommande::query()->pendingIntegration()->whereNull('integration_error')->count(),
            'integrated' => DbCommande::query()->integrated()->count(),
            'failed' => DbCommande::query()->failedIntegration()->count(),
        ];

        $payload = [
            'tab' => $tab,
            'importStats' => $importStats,
            'importMeta' => [
                'format' => 'excel',
                'templateUrl' => route('db-commandes.modele'),
                'acceptedExtensions' => ['xlsx', 'xls', 'csv'],
                'purgeConfirmationPhrase' => DbCommande::PURGE_ALL_CONFIRMATION_PHRASE,
            ],
            'referentiels' => [
                'zones' => $referentiels['zones'],
                'pharmacies' => $referentiels['pharmacies'],
                'arrondissements' => $referentiels['arrondissements'],
                'montantsLivraison' => $referentiels['montantsLivraison'],
                'modesPaiement' => $referentiels['modesPaiement'],
                'livreurs' => $referentiels['livreurs'],
                'parapharma_produit_types' => $referentiels['parapharma_produit_types'],
            ],
            'canManageCommandes' => $user->hasAnyRole(['admin', 'super_admin', 'agent_call_center']),
            'canDeleteCommandes' => $isImportAdmin,
            'canManageImports' => $isImportAdmin,
        ];

        $liveTotal = Commande::query()->whereNull('parent_id')->count();

        if ($tab === 'imports') {
            $payload = array_merge($payload, $this->importsIndexPayload($request));
            $payload['commandes'] = ['data' => [], 'links' => [], 'total' => $liveTotal];
        } else {
            $payload = array_merge($payload, $this->commandeIndexService->paginatedIndex($user, $request));
        }

        return Inertia::render('DbCommandes/Index', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function importsIndexPayload(Request $request): array
    {
        $search = trim((string) $request->input('import_search', ''));
        $statutFichier = trim((string) ($request->input('statut_fichier') ?: $request->input('import_statut', '')));
        $etatIntegration = trim((string) ($request->input('etat_integration') ?: $request->input('import_integration', '')));

        $query = DbCommande::query()
            ->with(['commande.client', 'commande.pharmacie'])
            ->orderByDesc('date_commande')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->search($search);
        }

        if ($statutFichier !== '') {
            $query->where('statut', $statutFichier);
        }

        if ($etatIntegration === 'pending') {
            $query->pendingIntegration()->whereNull('integration_error');
        } elseif ($etatIntegration === 'integrated') {
            $query->integrated();
        } elseif ($etatIntegration === 'failed') {
            $query->failedIntegration();
        }

        $imports = PaginatesSafely::paginate($query, $request, 15, 'import_page')->through(
            fn (DbCommande $c) => $this->toImportRow($c),
        );

        return [
            'imports' => $imports,
            'importFilters' => [
                'import_search' => $request->input('import_search'),
                'statut_fichier' => $statutFichier !== '' ? $statutFichier : null,
                'etat_integration' => $etatIntegration !== '' ? $etatIntegration : null,
            ],
            'statutsFichierImport' => \App\Models\Commande::STATUSES,
        ];
    }

    public function integrate(Request $request, DbCommande $dbCommande): RedirectResponse
    {
        $this->authorizeAdmin($request);

        try {
            $commande = $this->integrationService->integrateDbCommande($dbCommande);
        } catch (RuntimeException $e) {
            return $this->redirectToIndex('error', $e->getMessage(), 'imports');
        }

        return $this->redirectToIndex(
            'status',
            sprintf('Commande %s intégrée (n° %s).', $dbCommande->code_commande ?? '#'.$dbCommande->id, $commande->numero),
            'imports',
        );
    }

    public function integrateBulk(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:db_commandes,id'],
        ]);

        $result = $this->integrationService->integrateBulk($validated['ids']);

        return $this->redirectToIndex(
            'status',
            $this->formatIntegrationMessage($result),
            'imports',
        );
    }

    public function integrateAll(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $result = $this->integrationService->integrateAllPending();

        return $this->redirectToIndex(
            'status',
            $this->formatIntegrationMessage($result),
            'imports',
        );
    }

    public function destroy(Request $request, DbCommande $dbCommande): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($dbCommande->commande_id) {
            return $this->redirectToIndex(
                'error',
                'Impossible de supprimer une entrée déjà intégrée. Supprimez la commande live si nécessaire.',
            );
        }

        $dbCommande->delete();

        return $this->redirectToIndex('status', 'Entrée historique supprimée.', 'imports');
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:db_commandes,id'],
        ]);

        $n = DbCommande::query()
            ->whereIn('id', $validated['ids'])
            ->whereNull('commande_id')
            ->delete();

        return $this->redirectToIndex('status', $n > 1
            ? sprintf('%d entrées historiques supprimées.', $n)
            : 'Entrée historique supprimée.', 'imports');
    }

    public function purgeAll(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'confirmation' => ['required', Rule::in([DbCommande::PURGE_ALL_CONFIRMATION_PHRASE])],
        ]);

        $result = $this->stagingPurgeService->purgePendingImports();
        $count = $result['deleted'];

        return $this->redirectToIndex('status', $count > 0
            ? sprintf('%d ligne(s) importée(s) en attente supprimée(s). Les commandes intégrées et les comptes utilisateurs ne sont pas affectés.', $count)
            : 'Aucune ligne importée en attente à supprimer.', 'imports');
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'skip_duplicates' => ['sometimes', 'boolean'],
        ]);

        $file = $request->file('file');
        if ($file === null) {
            return back()->with('error', 'Aucun fichier reçu.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($extension, ['xlsx', 'xls', 'csv', 'txt'], true)) {
            return back()->with(
                'error',
                'Format non pris en charge. Utilisez un fichier Excel (.xlsx) ou CSV.',
            );
        }

        try {
            $result = $this->importService->importUploadedFile(
                $file,
                (bool) ($validated['skip_duplicates'] ?? true),
            );
        } catch (RuntimeException $e) {
            return $this->redirectToIndex('error', $e->getMessage(), 'imports');
        }

        return $this->redirectToIndex(
            'status',
            $this->formatImportMessage($result).' Consultez l’onglet « Import Excel ».',
            'imports',
        );
    }

    public function export(Request $request): Response
    {
        $this->authorizeView($request);

        $user = $request->user();
        abort_unless($user, 403);

        $path = storage_path('app/temp/commandes-export-'.uniqid('', true).'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $this->commandeExportService->writeExcel(
            $path,
            $user,
            $request->input('search'),
            $request->input('status'),
            $request->input('periode'),
            $request->input('date'),
        );

        $contents = file_get_contents($path);
        @unlink($path);

        if ($contents === false) {
            abort(500, 'Impossible de générer l’export Excel.');
        }

        $filename = 'commandes-export-'.now()->format('Y-m-d_His').'.xlsx';

        return response(
            $contents,
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }

    public function exportImports(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->input('import_search', ''));
        $statutFichier = trim((string) ($request->input('statut_fichier') ?: $request->input('import_statut', '')));
        $etatIntegration = trim((string) ($request->input('etat_integration') ?: $request->input('import_integration', '')));

        $path = storage_path('app/temp/imports-commandes-export-'.uniqid('', true).'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $this->importService->writeImportsExcel($path, function ($query) use ($search, $statutFichier, $etatIntegration) {
            if ($search !== '') {
                $query->search($search);
            }
            if ($statutFichier !== '') {
                $query->where('statut', $statutFichier);
            }
            if ($etatIntegration === 'pending') {
                $query->pendingIntegration()->whereNull('integration_error');
            } elseif ($etatIntegration === 'integrated') {
                $query->integrated();
            } elseif ($etatIntegration === 'failed') {
                $query->failedIntegration();
            }
        });

        $contents = file_get_contents($path);
        @unlink($path);

        if ($contents === false) {
            abort(500, 'Impossible de générer l’export Excel.');
        }

        $filename = 'imports-commandes-'.now()->format('Y-m-d_His').'.xlsx';

        return response(
            $contents,
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }

    public function destroyCommandesBulk(Request $request): RedirectResponse
    {
        $this->authorizeView($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:commandes,id'],
        ]);

        $user = $request->user();
        abort_unless($user, 403);

        foreach ($validated['ids'] as $id) {
            $commande = Commande::query()->findOrFail($id);
            $this->authorize('delete', $commande);
        }

        $count = $this->commandeAdminService->bulkDelete($user, $validated['ids']);

        return $this->redirectToIndex(
            'status',
            $count > 1
                ? sprintf('%d commande(s) supprimée(s).', $count)
                : 'Commande supprimée.',
            'commandes',
        );
    }

    public function downloadModele(Request $request): Response
    {
        $this->authorizeView($request);

        $path = storage_path('app/temp/db-commandes-modele-'.uniqid('', true).'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $this->importService->writeModeleExcel($path);
        $contents = file_get_contents($path);
        @unlink($path);

        if ($contents === false) {
            abort(500, 'Impossible de générer le modèle Excel.');
        }

        return response(
            $contents,
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="db-commandes-modele.xlsx"',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toImportRow(DbCommande $commande): array
    {
        return [
            'id' => $commande->id,
            'code_commande' => $commande->code_commande,
            'date_commande' => $commande->date_commande?->format('d/m/Y'),
            'nom_client' => $commande->nom_client,
            'telephone' => $commande->telephone,
            'pharmacie' => $commande->pharmacie,
            'medicaments' => $commande->medicaments,
            'total_paye_client' => $commande->total_paye_client !== null ? (float) $commande->total_paye_client : null,
            'statut_fichier' => $commande->statut,
            'statut_fichier_label' => $commande->statutFichierLabel(),
            'integrated' => $commande->isIntegrated(),
            'integrated_at' => $commande->integrated_at?->format('d/m/Y H:i'),
            'integration_error' => $commande->integration_error,
            'commande_id' => $commande->commande_id,
            'commande_numero' => $commande->commande?->numero,
            'source' => $commande->source,
        ];
    }

    private function authorizeView(Request $request): void
    {
        if (! $request->user()?->hasAnyRole(['admin', 'super_admin', 'agent_call_center'])) {
            abort(403);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        if (! $request->user()?->hasAnyRole(['admin', 'super_admin'])) {
            abort(403);
        }
    }

    /**
     * @param  array{imported: int, skipped: int, errors: list<string>, total: int}  $result
     */
    private function formatImportMessage(array $result, ?string $label = null): string
    {
        $prefix = $label ? $label.' : ' : '';
        $parts = [sprintf('%d commande(s) chargée(s)', $result['imported'])];

        if ($result['skipped'] > 0) {
            $parts[] = sprintf('%d doublon(s) ignoré(s)', $result['skipped']);
        }

        if ($result['errors'] !== []) {
            $parts[] = sprintf('%d erreur(s)', count($result['errors']));
        }

        return $prefix.implode(', ', $parts).'.';
    }

    /**
     * @param  array{integrated: int, failed: int, errors: list<string>}  $result
     */
    private function formatIntegrationMessage(array $result): string
    {
        $parts = [sprintf('%d intégrée(s) au système', $result['integrated'])];

        if ($result['failed'] > 0) {
            $parts[] = sprintf('%d échec(s)', $result['failed']);
        }

        $message = 'Intégration terminée — '.implode(', ', $parts).'.';

        if ($result['errors'] !== []) {
            $message .= ' '.implode(' | ', array_slice($result['errors'], 0, 3));
        }

        return $message;
    }

    private function redirectToIndex(string $flashKey, string $message, ?string $tab = null): RedirectResponse
    {
        $params = $tab ? ['tab' => $tab] : [];

        return redirect()
            ->route('db-commandes.index', $params)
            ->with($flashKey, $message);
    }
}
