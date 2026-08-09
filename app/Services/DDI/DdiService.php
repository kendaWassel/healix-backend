<?php

namespace App\Services\DDI;

use App\Exceptions\AI\AIServiceException;
use App\Exceptions\AI\AIServiceInvalidResponseException;
use Illuminate\Support\Facades\Log;

/**
 * Business layer for the DDI prediction microservice.
 *
 * Endpoints mirror C:\DDI-Prediction-System\main.py:
 * GET /interaction, POST /interaction/batch, POST /screen,
 * GET /allergy, GET /pregnancy.
 */
class DdiService
{
    public function __construct(
        protected DdiClient $client
    ) {}

    /**
     * Resolve a drug name (brand or generic) to its RxCUI identifier.
     *
     * The DDI service answers in one of three ways, and this method preserves
     * that contract so the caller/frontend can react appropriately:
     *   - resolved:   ['rxcui' => ..., 'source' => ..., 'resolved' => true]
     *   - suggestion: ['resolved' => false, 'suggestion' => ..., 'message' => ...]
     *                 (HTTP 200 — a "did you mean?" hint, NOT an error)
     *   - unknown:    the service returns HTTP 404, which surfaces here as an
     *                 AIServiceException the caller can translate to "not found".
     *
     * @throws AIServiceException
     */
    public function resolveDrug(string $name): array
    {
        Log::info('DDI name resolution started', [
            'name' => $name,
        ]);

        $result = $this->client->get('/resolve', [
            'name' => $name,
        ]);

        if (! array_key_exists('resolved', $result)) {
            throw new AIServiceInvalidResponseException(__('ai.ddi_no_resolution'));
        }

        return $result;
    }

    /**
     * Check one drug pair for an interaction (with severity and alternatives).
     *
     * @throws AIServiceException
     */
    public function checkInteraction(string $drugA, string $drugB, bool $includeAlternatives = true): array
    {
        Log::info('DDI interaction check started', [
            'drug_a' => $drugA,
            'drug_b' => $drugB,
        ]);

        $result = $this->client->get('/interaction', [
            'drug_a' => $drugA,
            'drug_b' => $drugB,
            'alternatives' => $includeAlternatives ? 'true' : 'false',
        ]);

        if (! isset($result['prediction'])) {
            throw new AIServiceInvalidResponseException(__('ai.ddi_no_prediction'));
        }

        return $result;
    }

    /**
     * Check several explicit drug pairs in one call.
     *
     * @param  array<int, array{drug_a: string, drug_b: string}>  $pairs
     *
     * @throws AIServiceException
     */
    public function checkInteractionBatch(array $pairs): array
    {
        $result = $this->client->post('/interaction/batch', [
            'pairs' => $pairs,
        ]);

        if (! isset($result['results']) || ! is_array($result['results'])) {
            throw new AIServiceInvalidResponseException(__('ai.ddi_no_batch_results'));
        }

        return $result;
    }

    /**
     * Screen a full medication list: every pairwise combination is checked and
     * only interacting pairs are returned, sorted by severity.
     *
     * @param  array<int, string>  $drugs
     *
     * @throws AIServiceException
     */
    public function screenMedications(array $drugs): array
    {
        Log::info('DDI medication screen started', [
            'drug_count' => count($drugs),
        ]);

        $result = $this->client->post('/screen', [
            'drugs' => array_values($drugs),
        ]);

        if (! isset($result['findings']) || ! is_array($result['findings'])) {
            throw new AIServiceInvalidResponseException(__('ai.ddi_no_findings'));
        }

        return $result;
    }

    /**
     * Check prescribed drugs against a patient's chronic conditions
     * (regulatory contraindication lookup, DrugCentral-backed).
     *
     * @param  array<int, string>  $medications
     * @param  array<int, string>  $conditions
     *
     * @throws AIServiceException
     */
    public function checkConditionContraindications(array $medications, array $conditions): array
    {
        Log::info('DDI condition check started', [
            'drug_count' => count($medications),
            'condition_count' => count($conditions),
        ]);

        $result = $this->client->post('/condition-check', [
            'medications' => array_values($medications),
            'conditions' => array_values($conditions),
        ]);

        if (! isset($result['warnings']) || ! is_array($result['warnings'])) {
            throw new AIServiceInvalidResponseException(__('ai.ddi_no_condition_warnings'));
        }

        return $result;
    }

    /**
     * Find drugs that may cross-react with one the patient is allergic to.
     *
     * Uses the DDI v2 POST /allergy endpoint in single-drug mode.
     *
     * @throws AIServiceException
     */
    public function checkAllergyCrossReactivity(string $drug, int $maxResults = 10): array
    {
        return $this->client->post('/allergy', [
            'drug' => $drug,
            'max_results' => $maxResults,
        ]);
    }

    /**
     * Prescription-mode allergy check: cross-check an entire medication list
     * against an entire allergy list in a single round-trip.
     *
     * Matches the DDI v2 POST /allergy "prescription-check" contract (as of
     * the service's 2026-08-08 allergy fix, commit 96fd9a6): ingredient-level
     * direct matches (e.g. Paracetamol vs. a Panadol allergy, resolved via
     * RxCUI — not just an exact-name match) plus structural/pharmacological
     * cross-reactive matches among the *prescribed* drugs only. The service
     * does not return the full non-prescribed candidate list in this mode.
     *
     * @param  array<int, string>  $medications  Prescribed drug names (brands or generics).
     * @param  array<int, string>  $allergies    Patient-known allergens.
     * @return array{
     *     direct_matches: array<int, array{medication: string, matched_ingredient: string, risk: string, note: string, allergen: string}>,
     *     cross_reactive_matches: array<int, array{name: string, tanimoto: ?float, detected_by: string, risk: string, allergen: string}>,
     *     safe: bool,
     * }
     *
     * @throws AIServiceException
     */
    public function checkPrescriptionAllergies(array $medications, array $allergies): array
    {
        $medications = array_values(array_filter($medications, fn ($m) => is_string($m) && trim($m) !== ''));
        $allergies = array_values(array_filter($allergies, fn ($a) => is_string($a) && trim($a) !== ''));

        if ($medications === [] || $allergies === []) {
            return [
                'direct_matches' => [],
                'cross_reactive_matches' => [],
                'safe' => true,
            ];
        }

        Log::info('DDI prescription-allergy check started', [
            'medication_count' => count($medications),
            'allergy_count' => count($allergies),
        ]);

        $result = $this->client->post('/allergy', [
            'medications' => $medications,
            'allergies' => $allergies,
        ]);

        foreach (['direct_matches', 'cross_reactive_matches'] as $key) {
            if (! isset($result[$key]) || ! is_array($result[$key])) {
                throw new AIServiceInvalidResponseException(__('ai.ddi_no_prescription_allergies'));
            }
        }

        return $result;
    }

    /**
     * Pregnancy safety for one drug, or the combined risk of two.
     *
     * @throws AIServiceException
     */
    public function checkPregnancySafety(string $drugA, ?string $drugB = null, bool $liveApi = true): array
    {
        $query = [
            'drug_a' => $drugA,
            'live_api' => $liveApi ? 'true' : 'false',
        ];

        if ($drugB !== null && $drugB !== '') {
            $query['drug_b'] = $drugB;
        }

        return $this->client->get('/pregnancy', $query);
    }
}
