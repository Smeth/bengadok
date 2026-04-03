<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\ClientFrequence;
use App\Models\Heur;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\MontantLivraison;
use App\Models\MotifAnnulation;
use App\Models\OrdonnanceVerificationSetting;
use App\Models\TypePharmacie;
use App\Models\Zone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParametresController extends Controller
{
    public function index(Request $request): Response
    {
        $motifsAnnulation = MotifAnnulation::query()
            ->withCount('commandes')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $allowedOnglets = [
            'zones',
            'modesPaiement',
            'montantsLivraison',
            'livreurs',
            'horaires',
            'typesPharmacie',
            'motifsAnnulation',
            'clientFrequences',
            'relanceCommande',
            'ordonnanceVerification',
        ];
        $onglet = $request->query('onglet');
        if (! is_string($onglet) || ! in_array($onglet, $allowedOnglets, true)) {
            $onglet = null;
        }

        $clientFrequences = ClientFrequence::query()
            ->orderByDesc('priorite')
            ->orderBy('designation')
            ->get()
            ->map(fn (ClientFrequence $f) => [
                'id' => $f->id,
                'designation' => $f->designation,
                'slug' => $f->slug,
                'commandes_minimum' => $f->commandes_minimum,
                'commandes_maximum' => $f->commandes_maximum,
                'intervalle_max_jours' => $f->intervalle_max_jours,
                'priorite' => $f->priorite,
            ]);

        return Inertia::render('settings/Parametres', [
            'zones' => Zone::withCount('pharmacies')->orderBy('designation')->get(),
            'modesPaiement' => ModePaiement::withCount('commandes')->orderBy('designation')->get(),
            'montantsLivraison' => MontantLivraison::withCount('commandes')->orderBy('designation')->get(),
            'livreurs' => Livreur::withCount('commandes')->orderBy('nom')->get(),
            'heurs' => Heur::withCount('pharmacies')->orderBy('ouverture')->get(),
            'typesPharmacie' => TypePharmacie::with('heurs')->withCount('pharmacies')->orderBy('designation')->get(),
            'motifsAnnulation' => $motifsAnnulation,
            'clientFrequences' => $clientFrequences,
            'appSettings' => [
                'delai_relance_meme_pharmacie_heures' => AppSetting::delaiRelanceMemePharmacieHeures(),
            ],
            'ordonnanceVerificationSettings' => $this->ordonnanceVerificationSettingsPayload(),
            'onglet' => $onglet,
        ]);
    }

    public function updateRelanceDelai(Request $request)
    {
        $validated = $request->validate([
            'delai_relance_meme_pharmacie_heures' => 'required|integer|min:0|max:8760',
        ]);

        $row = AppSetting::query()->first();
        if ($row) {
            $row->update($validated);
        } else {
            AppSetting::query()->create($validated);
        }

        return back()->with('status', 'Délai de relance (même pharmacie) enregistré.');
    }

    // ── Zones ──────────────────────────────────────────────────────────────

    public function storeZone(Request $request)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:zones,designation',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        Zone::create($validated);

        return back()->with('success', 'Zone créée avec succès.');
    }

    public function updateZone(Request $request, Zone $zone)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:zones,designation,'.$zone->id,
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $zone->update($validated);

        return back()->with('success', 'Zone mise à jour.');
    }

    public function destroyZone(Zone $zone)
    {
        if ($zone->pharmacies()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des pharmacies sont rattachées à cette zone.');
        }
        $zone->delete();

        return back()->with('success', 'Zone supprimée.');
    }

    // ── Modes de paiement ──────────────────────────────────────────────────

    public function storeModePaiement(Request $request)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:modes_paiement,designation',
        ]);
        ModePaiement::create($validated);

        return back()->with('success', 'Mode de paiement créé.');
    }

    public function updateModePaiement(Request $request, ModePaiement $modePaiement)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:modes_paiement,designation,'.$modePaiement->id,
        ]);
        $modePaiement->update($validated);

        return back()->with('success', 'Mode de paiement mis à jour.');
    }

    public function destroyModePaiement(ModePaiement $modePaiement)
    {
        if ($modePaiement->commandes()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des commandes utilisent ce mode de paiement.');
        }
        $modePaiement->delete();

        return back()->with('success', 'Mode de paiement supprimé.');
    }

    // ── Montants de livraison ──────────────────────────────────────────────

    public function storeMontantLivraison(Request $request)
    {
        $validated = $request->validate([
            'designation' => 'required|numeric|min:0',
        ]);
        MontantLivraison::create($validated);

        return back()->with('success', 'Montant de livraison créé.');
    }

    public function updateMontantLivraison(Request $request, MontantLivraison $montantLivraison)
    {
        $validated = $request->validate([
            'designation' => 'required|numeric|min:0',
        ]);
        $montantLivraison->update($validated);

        return back()->with('success', 'Montant de livraison mis à jour.');
    }

    public function destroyMontantLivraison(MontantLivraison $montantLivraison)
    {
        if ($montantLivraison->commandes()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des commandes utilisent ce montant.');
        }
        $montantLivraison->delete();

        return back()->with('success', 'Montant de livraison supprimé.');
    }

    // ── Livreurs ──────────────────────────────────────────────────────────

    public function storeLivreur(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'tel' => 'required|string|max:20',
        ]);
        Livreur::create($validated);

        return back()->with('success', 'Livreur créé.');
    }

    public function updateLivreur(Request $request, Livreur $livreur)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'tel' => 'required|string|max:20',
        ]);
        $livreur->update($validated);

        return back()->with('success', 'Livreur mis à jour.');
    }

    public function destroyLivreur(Livreur $livreur)
    {
        if ($livreur->commandes()->exists()) {
            return back()->with('error', 'Impossible de supprimer : ce livreur est associé à des commandes.');
        }
        $livreur->delete();

        return back()->with('success', 'Livreur supprimé.');
    }

    // ── Horaires ──────────────────────────────────────────────────────────

    public function storeHeur(Request $request)
    {
        $validated = $request->validate([
            'ouverture' => 'required|string|max:5',
            'fermeture' => 'required|string|max:5',
        ]);
        Heur::create($validated);

        return back()->with('success', 'Horaire créé.');
    }

    public function updateHeur(Request $request, Heur $heur)
    {
        $validated = $request->validate([
            'ouverture' => 'required|string|max:5',
            'fermeture' => 'required|string|max:5',
        ]);
        $heur->update($validated);

        return back()->with('success', 'Horaire mis à jour.');
    }

    public function destroyHeur(Heur $heur)
    {
        if ($heur->typePharmacies()->exists() || $heur->pharmacies()->exists()) {
            return back()->with('error', 'Impossible de supprimer : cet horaire est utilisé par des pharmacies.');
        }
        $heur->delete();

        return back()->with('success', 'Horaire supprimé.');
    }

    // ── Types de pharmacie ─────────────────────────────────────────────────

    public function storeTypePharmacie(Request $request)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:type_pharmacies,designation',
            'heurs_id' => 'nullable|exists:heurs,id',
        ]);
        TypePharmacie::create($validated);

        return back()->with('success', 'Type de pharmacie créé.');
    }

    public function updateTypePharmacie(Request $request, TypePharmacie $typePharmacie)
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:100|unique:type_pharmacies,designation,'.$typePharmacie->id,
            'heurs_id' => 'nullable|exists:heurs,id',
        ]);
        $typePharmacie->update($validated);

        return back()->with('success', 'Type de pharmacie mis à jour.');
    }

    public function destroyTypePharmacie(TypePharmacie $typePharmacie)
    {
        if ($typePharmacie->pharmacies()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des pharmacies utilisent ce type.');
        }
        $typePharmacie->delete();

        return back()->with('success', 'Type de pharmacie supprimé.');
    }

    // ── Motifs d'annulation (dynamiques) ───────────────────────────────────

    public function storeMotifAnnulation(Request $request)
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', MotifAnnulation::uniqueSlugRule()],
            'label' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:999999',
        ]);

        $maxOrder = (int) MotifAnnulation::query()->max('sort_order');
        MotifAnnulation::create([
            'slug' => $validated['slug'],
            'label' => $validated['label'],
            'autorise_relance' => $request->boolean('autorise_relance'),
            'sort_order' => $validated['sort_order'] ?? $maxOrder + 1,
        ]);

        return back()->with('success', 'Motif d\'annulation créé.');
    }

    public function updateMotifAnnulation(Request $request, MotifAnnulation $motifAnnulation)
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', MotifAnnulation::uniqueSlugRule($motifAnnulation->id)],
            'label' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:999999',
        ]);

        if ($validated['slug'] !== $motifAnnulation->slug && $motifAnnulation->commandes()->exists()) {
            return back()->with('error', 'Impossible de modifier le code : des commandes utilisent déjà ce motif.');
        }

        $motifAnnulation->update([
            'slug' => $validated['slug'],
            'label' => $validated['label'],
            'autorise_relance' => $request->boolean('autorise_relance'),
            'sort_order' => $validated['sort_order'] ?? $motifAnnulation->sort_order,
        ]);

        return back()->with('success', 'Motif d\'annulation mis à jour.');
    }

    public function destroyMotifAnnulation(MotifAnnulation $motifAnnulation)
    {
        if (MotifAnnulation::query()->count() <= 1) {
            return back()->with('error', 'Conservez au moins un motif d\'annulation.');
        }
        if ($motifAnnulation->commandes()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des commandes sont associées à ce motif.');
        }
        $motifAnnulation->delete();

        return back()->with('success', 'Motif d\'annulation supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function ordonnanceVerificationSettingsPayload(): array
    {
        $row = OrdonnanceVerificationSetting::query()->first();
        if (! $row) {
            return [
                'enabled' => true,
                'execution_mode' => OrdonnanceVerificationSetting::EXECUTION_MODE_QUEUE,
                'max_prescription_age_days' => 180,
                'threshold_pass' => 70,
                'threshold_review' => 45,
                'block_pass_on_duplicate' => true,
                'tesseract_binary' => 'tesseract',
                'rule_weights' => [],
                'keywords_prescriber' => [],
                'keywords_patient' => [],
                'keywords_medicament' => [],
            ];
        }

        return [
            'enabled' => $row->enabled,
            'execution_mode' => $row->execution_mode
                ?? OrdonnanceVerificationSetting::EXECUTION_MODE_QUEUE,
            'max_prescription_age_days' => $row->max_prescription_age_days,
            'threshold_pass' => $row->threshold_pass,
            'threshold_review' => $row->threshold_review,
            'block_pass_on_duplicate' => $row->block_pass_on_duplicate,
            'tesseract_binary' => $row->tesseract_binary,
            'rule_weights' => $row->rule_weights ?? [],
            'keywords_prescriber' => $row->keywords_prescriber ?? [],
            'keywords_patient' => $row->keywords_patient ?? [],
            'keywords_medicament' => $row->keywords_medicament ?? [],
        ];
    }

    public function updateOrdonnanceVerification(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'sometimes|boolean',
            'execution_mode' => 'required|in:queue,immediate',
            'max_prescription_age_days' => 'required|integer|min:1|max:3650',
            'threshold_pass' => 'required|integer|min:0|max:100',
            'threshold_review' => 'required|integer|min:0|max:100',
            'block_pass_on_duplicate' => 'boolean',
            'tesseract_binary' => 'required|string|max:255',
            'rule_weights' => 'required|array',
            'rule_weights.date_found' => 'nullable|integer|min:0|max:100',
            'rule_weights.date_not_future' => 'nullable|integer|min:0|max:100',
            'rule_weights.date_within_max_age' => 'nullable|integer|min:0|max:100',
            'rule_weights.prescriber_keywords' => 'nullable|integer|min:0|max:100',
            'rule_weights.patient_keywords' => 'nullable|integer|min:0|max:100',
            'rule_weights.medicament_keywords' => 'nullable|integer|min:0|max:100',
            'rule_weights.no_duplicate_file' => 'nullable|integer|min:0|max:100',
            'keywords_prescriber' => 'required|array',
            'keywords_prescriber.*' => 'string|max:255',
            'keywords_patient' => 'required|array',
            'keywords_patient.*' => 'string|max:255',
            'keywords_medicament' => 'required|array',
            'keywords_medicament.*' => 'string|max:255',
        ]);

        if ($validated['threshold_review'] > $validated['threshold_pass']) {
            return back()->with('error', 'Le seuil « revue » doit être inférieur ou égal au seuil « conforme ».');
        }

        $row = OrdonnanceVerificationSetting::query()->first();
        if (! $row) {
            OrdonnanceVerificationSetting::query()->create([
                'enabled' => $request->boolean('enabled'),
                'execution_mode' => $validated['execution_mode'],
                'max_prescription_age_days' => $validated['max_prescription_age_days'],
                'threshold_pass' => $validated['threshold_pass'],
                'threshold_review' => $validated['threshold_review'],
                'rule_weights' => array_map('intval', $validated['rule_weights']),
                'keywords_prescriber' => array_values(array_filter(array_map('trim', $validated['keywords_prescriber']))),
                'keywords_patient' => array_values(array_filter(array_map('trim', $validated['keywords_patient']))),
                'keywords_medicament' => array_values(array_filter(array_map('trim', $validated['keywords_medicament']))),
                'block_pass_on_duplicate' => $request->boolean('block_pass_on_duplicate'),
                'tesseract_binary' => $validated['tesseract_binary'],
            ]);
        } else {
            $row->update([
                'enabled' => $request->boolean('enabled'),
                'execution_mode' => $validated['execution_mode'],
                'max_prescription_age_days' => $validated['max_prescription_age_days'],
                'threshold_pass' => $validated['threshold_pass'],
                'threshold_review' => $validated['threshold_review'],
                'rule_weights' => array_map('intval', $validated['rule_weights']),
                'keywords_prescriber' => array_values(array_filter(array_map('trim', $validated['keywords_prescriber']))),
                'keywords_patient' => array_values(array_filter(array_map('trim', $validated['keywords_patient']))),
                'keywords_medicament' => array_values(array_filter(array_map('trim', $validated['keywords_medicament']))),
                'block_pass_on_duplicate' => $request->boolean('block_pass_on_duplicate'),
                'tesseract_binary' => $validated['tesseract_binary'],
            ]);
        }

        return back()->with('status', 'Paramètres de vérification des ordonnances enregistrés.');
    }
}
