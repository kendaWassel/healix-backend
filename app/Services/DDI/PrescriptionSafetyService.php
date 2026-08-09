<?php

namespace App\Services\DDI;

use App\Exceptions\AI\AIServiceException;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\AI\ConditionCheckService;
use App\Support\Locale;
use Illuminate\Support\Facades\Log;

/**
 * Verifies the clinical safety of a prescription before it is priced.
 *
 * Orchestrates the existing DDI microservice across four checks — drug
 * interactions, allergy conflicts, pregnancy safety and chronic-condition
 * contraindications — and merges them into one unified report. No clinical/ML
 * logic lives here; it is purely composition over the DDI services.
 */
class PrescriptionSafetyService
{
    /** Pregnancy categories serious enough to warn a pharmacist about. */
    protected const PREGNANCY_WARNING_CATEGORIES = ['X', 'D', 'D*', 'C'];

    public function __construct(
        protected DdiService $ddi,
        protected ConditionCheckService $conditionCheck,
    ) {}

    /**
     * Build the full safety report for a saved prescription (pharmacist flow).
     *
     * Thin adapter over verifyDraft(): pulls the patient and medication names
     * off the persisted prescription, then runs the shared verification.
     *
     * @return array<string, mixed>
     *
     * @throws AIServiceException When the DDI service is unreachable for the
     *                            interaction screen (a hard failure).
     */
    public function verify(Prescription $prescription): array
    {
        $prescription->loadMissing(['medications.medication', 'patient']);

        return $this->verifyDraft(
            $prescription->patient,
            $this->medicationNames($prescription)
        );
    }

    /**
     * Build the full safety report for a DRAFT prescription — a patient plus a
     * plain medication-name list — without needing a saved Prescription model.
     *
     * This is the single reusable verification entry point, shared by the
     * pharmacist (via verify()) and the doctor's pre-save decision-support
     * check. It performs NO persistence.
     *
     * @param  array<int, string>  $medications
     * @return array<string, mixed>
     *
     * @throws AIServiceException When the DDI service is unreachable for the
     *                            interaction screen (a hard failure).
     */
    public function verifyDraft(?Patient $patient, array $medications): array
    {
        $medications = $this->normalizeNames($medications);

        $drugInteractions = $this->drugInteractions($medications);
        $allergyWarnings = $this->allergyWarnings($medications, $patient);
        [$pregnancyApplicable, $pregnancyWarnings, $pregnancyNote] = $this->pregnancyWarnings($medications, $patient);
        $condition = $this->conditionCheck->check($medications, $this->patientConditions($patient));
        $conditionWarnings = $this->conditionWarnings($condition['warnings']);

        $safe = $drugInteractions === []
            && $allergyWarnings === []
            && $pregnancyWarnings === []
            && $conditionWarnings === [];

        return [
            'safe' => $safe,
            'medications_checked' => $medications,
            'drug_interactions' => $drugInteractions,
            'allergy_warnings' => $allergyWarnings,
            'pregnancy_applicable' => $pregnancyApplicable,
            'pregnancy_warnings' => $pregnancyWarnings,
            'pregnancy_note' => $pregnancyNote,
            'condition_warnings' => $conditionWarnings,
            'condition_check_available' => $condition['available'],
        ];
    }

    /**
     * Distinct, non-empty medication names on the prescription.
     *
     * @return array<int, string>
     */
    protected function medicationNames(Prescription $prescription): array
    {
        return $this->normalizeNames(
            $prescription->medications->map(fn ($item) => $item->medication?->name)
        );
    }

    /**
     * Trim, drop empties, and de-duplicate (case-insensitive) a list of names.
     *
     * @param  iterable<mixed>  $names
     * @return array<int, string>
     */
    protected function normalizeNames(iterable $names): array
    {
        return collect($names)
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => trim($name))
            ->unique(fn ($name) => mb_strtolower($name))
            ->values()
            ->all();
    }

    /**
     * Screen every prescribed pair for interactions (reuses /screen), then
     * enrich each interacting pair with safer-drug alternatives.
     *
     * /screen returns the interacting pairs but not their alternatives, so each
     * finding is topped up via /interaction (which does). A per-pair lookup
     * failure just omits alternatives for that pair — it never fails the screen.
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

        $findings = $this->ddi->screenMedications($medications)['findings'] ?? [];

        foreach ($findings as &$finding) {
            // Localized labels alongside the raw enum values (frontend keys off raw).
            $finding['severity_label'] = Locale::label('ddi_severity', $finding['severity'] ?? null);
            $finding['severity_confidence_label'] = Locale::label('ddi_confidence', $finding['severity_confidence'] ?? null);

            $finding['alternatives'] = $this->interactionAlternatives(
                (string) ($finding['drug_a'] ?? ''),
                (string) ($finding['drug_b'] ?? '')
            );
            $finding['alternatives'] = $this->localizeAlternativesNote(
                $finding['alternatives'],
                (string) ($finding['drug_b'] ?? ''),
                (string) ($finding['drug_a'] ?? '')
            );
        }
        unset($finding);

        return $findings;
    }

    /**
     * Safer-drug alternatives for one interacting pair (reuses /interaction).
     *
     * @return array<string, mixed> The DDI `alternatives` block
     *                              ({atc_class, candidates, note}), or [] on failure.
     */
    protected function interactionAlternatives(string $drugA, string $drugB): array
    {
        if ($drugA === '' || $drugB === '') {
            return [];
        }

        try {
            $result = $this->ddi->checkInteraction($drugA, $drugB);
        } catch (AIServiceException $e) {
            Log::info('Interaction alternatives lookup skipped', [
                'drug_a' => $drugA,
                'drug_b' => $drugB,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return $result['alternatives'] ?? [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array<int, array<string, mixed>>
     */
    protected function conditionWarnings(array $warnings): array
    {
        return array_values(array_map(function (array $warning): array {
            $conditionLabel = Locale::label('ddi_condition', $warning['condition'] ?? null, $warning['condition'] ?? null);
            $patientConditionLabel = Locale::label(
                'ddi_condition',
                $warning['patient_condition'] ?? null,
                $warning['patient_condition'] ?? $conditionLabel
            );

            $warning['condition_label'] = $conditionLabel;
            $warning['patient_condition_label'] = $patientConditionLabel;
            $warning['note'] = __('ai.safety_condition_contraindicated_note', [
                'medication' => $warning['medication'] ?? '',
                'ingredient' => $warning['resolved_ingredient'] ?? ($warning['medication'] ?? ''),
                'condition' => $conditionLabel,
            ]);

            return $warning;
        }, $warnings));
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

            // Fetch the allergen's cross-reactive drugs once, and reduce them to
            // plain names — attached to every warning below so the prescriber
            // sees the other drugs to avoid (parallels interaction alternatives).
            $crossReactive = $this->allergenCrossReactives($allergen);
            $crossReactiveNames = array_values(array_filter(array_map(
                fn ($candidate) => $candidate['name'] ?? null,
                $crossReactive
            )));

            // 1) Direct allergy — a prescribed drug IS the allergen.
            foreach ($medications as $med) {
                if (mb_strtolower($med) === $allergenLower) {
                    $warnings[$med . '|' . $allergen] = [
                        'medication' => $med,
                        'allergen' => $allergen,
                        'detected_by' => 'direct_match',
                        'detected_by_label' => Locale::label('ddi_detected_by', 'direct_match'),
                        'risk' => 'HIGH',
                        'risk_label' => Locale::label('ddi_risk', 'HIGH'),
                        'note' => __('ai.safety_allergy_direct_note'),
                        'cross_reactive_drugs' => $crossReactiveNames,
                    ];
                }
            }

            // 2) Cross-reactivity — a prescribed drug shares structure/class with the allergen.
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

                    $detectedBy = $candidate['detected_by'] ?? 'cross_reactivity';
                    $risk = $candidate['risk'] ?? 'MODERATE';

                    $warnings[$med . '|' . $allergen] ??= [
                        'medication' => $med,
                        'allergen' => $allergen,
                        'detected_by' => $detectedBy,
                        'detected_by_label' => Locale::label('ddi_detected_by', $detectedBy),
                        'risk' => $risk,
                        'risk_label' => Locale::label('ddi_risk', $risk),
                        'tanimoto' => $candidate['tanimoto'] ?? null,
                        'note' => __('ai.safety_allergy_cross_note', ['allergen' => $allergen]),
                        'cross_reactive_drugs' => $crossReactiveNames,
                    ];
                }
            }
        }

        return array_values($warnings);
    }

    /**
     * The allergen's cross-reactive drugs (reuses /allergy). Returns the raw
     * candidate objects, or [] if the allergen is unknown / the lookup fails —
     * a failure never breaks the verification.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function allergenCrossReactives(string $allergen): array
    {
        try {
            $response = $this->ddi->checkAllergyCrossReactivity($allergen);
        } catch (AIServiceException $e) {
            Log::info('Allergy cross-reactivity check skipped', [
                'allergen' => $allergen,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return $response['cross_reactive_drugs'] ?? [];
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
            return [false, [], __('ai.safety_pregnancy_not_applicable')];
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
                    'category_label' => Locale::label('ddi_pregnancy_category', $category),
                    'warning' => $result['warning'] ?? null,
                    'pllr_note' => $result['pllr_note'] ?? null,
                ];
            }
        }

        return [true, $warnings, null];
    }

    /**
     * Localize the alternatives note using the pair context we already have.
     */
    protected function localizeAlternativesNote(array $alternatives, string $referenceDrug, string $otherDrug): array
    {
        if ($alternatives === [] || ! array_key_exists('note', $alternatives)) {
            return $alternatives;
        }

        $alternatives['note'] = __('ai.safety_interaction_alternative_note', [
            'reference_drug' => $referenceDrug,
            'other_drug' => $otherDrug,
        ]);

        return $alternatives;
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
            foreach ($this->fieldToList($record->allergies) as $allergen) {
                $allergens[mb_strtolower($allergen)] = $allergen;
            }
        }

        return array_values($allergens);
    }

    /**
     * Normalise a medical-record list field to trimmed string terms.
     *
     * `allergies` / `chronic_diseases` are now cast to arrays, but legacy rows
     * (and some seeds) may still be plain / comma-joined strings, so accept
     * both shapes rather than crash.
     *
     * @param  mixed  $value
     * @return array<int, string>
     */
    protected function fieldToList($value): array
    {
        $items = is_array($value) ? $value : $this->splitList(is_string($value) ? $value : null);

        return array_values(array_filter(
            array_map(fn ($item) => trim((string) $item), $items),
            fn ($item) => $item !== ''
        ));
    }

    /**
     * Distinct chronic-condition names from the patient's medical records.
     *
     * `chronic_diseases` is a JSON array of DrugCentral-standard names (the
     * app's condition picker stores the English `value`). Legacy free-text /
     * comma-joined values are tolerated so old rows still resolve. The separate
     * `other_conditions` free-text field is intentionally NOT included — it is
     * for clinician review only, not the automatic contraindication check.
     *
     * @return array<int, string>
     */
    protected function patientConditions(?Patient $patient): array
    {
        if ($patient === null) {
            return [];
        }

        $records = $patient->relationLoaded('medicalRecords')
            ? $patient->medicalRecords
            : $patient->medicalRecords()->get();

        $conditions = [];

        foreach ($records as $record) {
            foreach ($this->fieldToList($record->chronic_diseases) as $condition) {
                $conditions[mb_strtolower($condition)] = $condition;
            }
        }

        return array_values($conditions);
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
