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
            throw new AIServiceInvalidResponseException('DDI service did not return an interaction prediction.');
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
            throw new AIServiceInvalidResponseException('DDI service did not return batch results.');
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
            throw new AIServiceInvalidResponseException('DDI service did not return screening findings.');
        }

        return $result;
    }

    /**
     * Find drugs that may cross-react with one the patient is allergic to.
     *
     * @throws AIServiceException
     */
    public function checkAllergyCrossReactivity(string $drug, int $maxResults = 10): array
    {
        return $this->client->get('/allergy', [
            'drug' => $drug,
            'max_results' => $maxResults,
        ]);
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
