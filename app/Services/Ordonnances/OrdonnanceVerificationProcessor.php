<?php

namespace App\Services\Ordonnances;

use App\Models\Ordonnance;
use App\Models\OrdonnanceVerification;
use App\Models\OrdonnanceVerificationSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class OrdonnanceVerificationProcessor
{
    public function __construct(
        private OrdonnanceOcrExtractor $ocrExtractor,
        private OrdonnancePrescriptionDateParser $dateParser,
    ) {}

    public function process(int $ordonnanceId): void
    {
        $ordonnance = Ordonnance::query()->find($ordonnanceId);
        if (! $ordonnance) {
            return;
        }

        $settings = OrdonnanceVerificationSetting::query()->first();
        if (! $settings) {
            return;
        }

        $verification = OrdonnanceVerification::query()->firstOrCreate(
            ['ordonnance_id' => $ordonnance->id],
            ['status' => 'pending', 'decision' => 'pending']
        );

        $verification->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        if (! $settings->enabled) {
            $verification->update([
                'status' => 'completed',
                'decision' => 'skipped',
                'score' => null,
                'ocr_text' => null,
                'rule_results' => ['message' => 'Vérification désactivée dans les paramètres.'],
                'flags' => [],
                'processed_at' => now(),
            ]);

            return;
        }

        try {
            $absolute = Storage::disk('public')->path($ordonnance->urlfile);
            $ocrText = $this->ocrExtractor->extractText($absolute, $settings->tesseract_binary);
            $lower = mb_strtolower($ocrText);

            $duplicateId = $this->findDuplicateOrdonnance($ordonnance);
            $dates = $this->dateParser->parseAllDatesFromText($ocrText);
            $today = Carbon::now()->startOfDay();
            $picked = $this->dateParser->pickPrescriptionDate($dates, $today);

            $ruleResults = $this->evaluateRules(
                $settings,
                $lower,
                $picked,
                $today,
                $duplicateId !== null,
                count($dates) > 0,
            );

            $weights = $settings->rule_weights ?? [];
            $maxPoints = 0;
            $earned = 0;
            foreach ($ruleResults as $key => $info) {
                $w = (int) ($weights[$key] ?? 0);
                if ($w <= 0) {
                    continue;
                }
                $maxPoints += $w;
                if (! empty($info['pass'])) {
                    $earned += $w;
                }
            }

            $score = $maxPoints > 0 ? (int) round($earned / $maxPoints * 100) : 0;

            $flags = [
                'date_future' => $picked !== null && $picked->gt($today),
                'date_too_old' => $picked !== null && $picked->lt($today->copy()->subDays($settings->max_prescription_age_days)),
                'duplicate_file' => $duplicateId !== null,
                'ocr_empty' => $ocrText === '',
            ];

            $decision = $this->decideDecision($score, $settings, $duplicateId);

            $verification->update([
                'status' => 'completed',
                'ocr_text' => mb_substr($ocrText, 0, 65000),
                'parsed_prescription_date' => $picked,
                'score' => $score,
                'decision' => $decision,
                'rule_results' => $ruleResults,
                'flags' => $flags,
                'duplicate_of_ordonnance_id' => $duplicateId,
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $verification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }

    private function findDuplicateOrdonnance(Ordonnance $ordonnance): ?int
    {
        $hash = $ordonnance->file_hash_sha256;
        if ($hash === null || $hash === '') {
            return null;
        }

        $other = Ordonnance::query()
            ->where('file_hash_sha256', $hash)
            ->where('id', '!=', $ordonnance->id)
            ->orderBy('id')
            ->first();

        return $other?->id;
    }

    /**
     * @return array<string, array{pass: bool, label: string}>
     */
    private function evaluateRules(
        OrdonnanceVerificationSetting $settings,
        string $lowerText,
        ?Carbon $picked,
        Carbon $today,
        bool $isDuplicate,
        bool $anyDateFound
    ): array {
        $hasPrescriber = $this->matchAnyKeyword($lowerText, $settings->keywords_prescriber ?? []);
        $hasPatient = $this->matchAnyKeyword($lowerText, $settings->keywords_patient ?? []);
        $hasMedic = $this->matchAnyKeyword($lowerText, $settings->keywords_medicament ?? []);

        $dateFound = $picked !== null || $anyDateFound;
        $notFuture = $picked === null || $picked->lte($today);
        $minDate = $today->copy()->subDays($settings->max_prescription_age_days);
        $withinAge = $picked !== null && $picked->gte($minDate) && $picked->lte($today);

        return [
            'date_found' => [
                'pass' => $dateFound,
                'label' => 'Date de prescription détectée dans le texte',
            ],
            'date_not_future' => [
                'pass' => $notFuture,
                'label' => 'Date non postérieure à aujourd’hui',
            ],
            'date_within_max_age' => [
                'pass' => $withinAge,
                'label' => 'Date dans la fenêtre d’âge maximum (éligibilité)',
            ],
            'prescriber_keywords' => [
                'pass' => $hasPrescriber,
                'label' => 'Indices prescripteur / structure (mots-clés)',
            ],
            'patient_keywords' => [
                'pass' => $hasPatient,
                'label' => 'Indices patient (mots-clés)',
            ],
            'medicament_keywords' => [
                'pass' => $hasMedic,
                'label' => 'Indices médicaments (mots-clés)',
            ],
            'no_duplicate_file' => [
                'pass' => ! $isDuplicate,
                'label' => 'Fichier non déjà utilisé (empreinte)',
            ],
        ];
    }

    private function decideDecision(
        int $score,
        OrdonnanceVerificationSetting $settings,
        ?int $duplicateId
    ): string {
        if ($settings->block_pass_on_duplicate && $duplicateId !== null) {
            return 'review';
        }

        if ($score >= $settings->threshold_pass) {
            return 'pass';
        }

        if ($score >= $settings->threshold_review) {
            return 'review';
        }

        return 'fail';
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function matchAnyKeyword(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            $k = mb_strtolower(trim((string) $kw));
            if ($k !== '' && str_contains($haystack, $k)) {
                return true;
            }
        }

        return false;
    }
}
