<?php

namespace App\Services\DDI;

use App\Exceptions\AI\AIServiceException;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Support\Facades\Log;

/**
 * Verifies the clinical safety of a prescription before it is priced.
 *
 * Orchestrates the existing DDI microservice (via DdiService) across three
 * checks — drug interactions, allergy conflicts and pregnancy safety — and
 * merges them into one unified report. No clinical/ML logic lives here; it is
 * purely composition over DdiService.
 */
class PrescriptionSafetyService
{
    /** Pregnancy categories serious enough to warn a pharmacist about. */
    protected const PREGNANCY_WARNING_CATEGORIES = ['X', 'D', 'D*', 'C'];

    public function __construct(
        protected DdiService $ddi
    ) {}

    /**
     * Build the full safety report for a prescription.
     *
     * @return array<string, mixed>
     *
     * @throws AIServiceException When the DDI service is unreachable for the
     *                            interaction screen (a hard failure).
     */
    public function verify(Prescription $prescription): array
    {
        $prescription->loadMissing(['medications.medication', 'patient']);

        $medications = $this->medicationNames($prescription);
        $patient = $prescription->patient;

        $drugInteractions = $this->drugInteractions($medications);
        $allergyWarnings = $this->allergyWarnings($medications, $patient);
        [$pregnancyApplicable, $pregnancyWarnings, $pregnancyNote] = $this->pregnancyWarnings($medications, $patient);

        $safe = $drugInteractions === []
            && $allergyWarnings === []
            && $pregnancyWarnings === [];

        return [
            'safe' => $safe,
            'medications_checked' => $medications,
            'drug_interactions' => $drugInteractions,
            'allergy_warnings' => $allergyWarnings,
            'pregnancy_applicable' => $pregnancyApplicable,
            'pregnancy_warnings' => $pregnancyWarnings,
            'pregnancy_note' => $pregnancyNote,
        ];
    }

    /**
     * Distinct, non-empty medication names on the prescription.
     *
     * @return array<int, string>
     */
    protected function medicationNames(Prescription $prescription): array
    {
        return $prescription->medications
            ->map(fn ($item) => $item->medication?->name)
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => trim($name))
            ->unique(fn ($name) => mb_strtolower($name))
            ->values()
            ->all();
    }

    /**
     * Screen every prescribed pair for interactions (reuses /screen).
     *
     * @param  array<int, string>  $medications
     * @return array<int, array<string, mixed>>
     *
     * @throws AIServiceException
     */
    protected function drugInteractions(array $medications): array
    {
        // A pairwise screen needs at least two drugs.
        if (count($medications) < 2) {
            return [];
        }

        $result = $this->ddi->screenMedications($medications);

        return $result['findings'] ?? [];
    }

    /**
     * Cross-check each prescribed drug against the patient's recorded allergies,
     * both directly and via structural/pharmacological cross-reactivity (/allergy).
     *
     * @param  array<int, string>  $medications
     * @return array<int, array<string, mixed>>
     */
    protected function allergyWarnings(array $medications, ?Patient $patient): array
    {
        $allergens = $this->patientAllergens($patient);

        if ($medications === [] || $allergens === []) {
            return [];
        }

        $warnings = [];

        foreach ($allergens as $allergen) {
            $allergenLower = mb_strtolower($allergen);

            // 1) Direct allergy — a prescribed drug IS the allergen.
            foreach ($medications as $med) {
                if (mb_strtolower($med) === $allergenLower) {
                    $warnings[$med . '|' . $allergen] = [
                        'medication' => $med,
                        'allergen' => $allergen,
                        'detected_by' => 'direct_match',
                        'risk' => 'HIGH',
                        'note' => 'Patient is directly allergic to this medication.',
                    ];
                }
            }

            // 2) Cross-reactivity — reuse the DDI allergy engine.
            try {
                $response = $this->ddi->checkAllergyCrossReactivity($allergen);
            } catch (AIServiceException $e) {
                // Unknown allergen (404) or a transient error: skip this allergen
                // rather than failing the whole verification.
                Log::info('Allergy cross-reactivity check skipped', [
                    'allergen' => $allergen,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $crossReactive = $response['cross_reactive_drugs'] ?? [];

            foreach ($crossReactive as $candidate) {
                $candidateName = $candidate['name'] ?? null;
                if (! is_string($candidateName)) {
                    continue;
                }
                $candidateLower = mb_strtolower($candidateName);

                foreach ($medications as $med) {
                    if (mb_strtolower($med) !== $candidateLower) {
                        continue;
                    }

                    $warnings[$med . '|' . $allergen] ??= [
                        'medication' => $med,
                        'allergen' => $allergen,
                        'detected_by' => $candidate['detected_by'] ?? 'cross_reactivity',
                        'risk' => $candidate['risk'] ?? 'MODERATE',
                        'tanimoto' => $candidate['tanimoto'] ?? null,
                        'note' => "May cross-react with the patient's allergy to {$allergen}.",
                    ];
                }
            }
        }

        return array_values($warnings);
    }

    /**
     * Per-drug pregnancy safety, only when the patient is recorded as pregnant.
     *
     * @param  array<int, string>  $medications
     * @return array{0: bool, 1: array<int, array<string, mixed>>, 2: string|null}
     */
    protected function pregnancyWarnings(array $medications, ?Patient $patient): array
    {
        if (! $this->isPregnant($patient)) {
            return [false, [], 'Patient is not recorded as pregnant — pregnancy check skipped.'];
        }

        if ($medications === []) {
            return [true, [], null];
        }

        $warnings = [];

        foreach ($medications as $med) {
            try {
                $result = $this->ddi->checkPregnancySafety($med);
            } catch (AIServiceException $e) {
                Log::info('Pregnancy safety check skipped', [
                    'medication' => $med,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $category = $result['category'] ?? 'Unknown';

            if (in_array($category, self::PREGNANCY_WARNING_CATEGORIES, true)) {
                $warnings[] = [
                    'medication' => $med,
                    'category' => $category,
                    'warning' => $result['warning'] ?? null,
                    'pllr_note' => $result['pllr_note'] ?? null,
                ];
            }
        }

        return [true, $warnings, null];
    }

    /**
     * Whether the patient is currently pregnant, read from their most recent
     * medical record.
     */
    protected function isPregnant(?Patient $patient): bool
    {
        if ($patient === null) {
            return false;
        }

        // latest('id') matches the record the patient's pregnancy endpoint writes to.
        $record = $patient->medicalRecords()->latest('id')->first();

        return $record !== null && (bool) $record->is_pregnant;
    }

    /**
     * Distinct allergen names parsed from the patient's medical records.
     *
     * @return array<int, string>
     */
    protected function patientAllergens(?Patient $patient): array
    {
        if ($patient === null) {
            return [];
        }

        $records = $patient->relationLoaded('medicalRecords')
            ? $patient->medicalRecords
            : $patient->medicalRecords()->get();

        $allergens = [];

        foreach ($records as $record) {
            foreach ($this->splitList($record->allergies) as $allergen) {
                $allergens[mb_strtolower($allergen)] = $allergen;
            }
        }

        return array_values($allergens);
    }

    /**
     * Split a free-text list ("Penicillin, Aspirin; Sulfa") into trimmed terms.
     *
     * @return array<int, string>
     */
    protected function splitList(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $parts = preg_split('/[,;\n\r]+|\band\b/i', $text) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }
}
