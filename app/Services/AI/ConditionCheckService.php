<?php

namespace App\Services\AI;

use App\Exceptions\AI\AIServiceException;
use App\Services\DDI\DdiService;
use Illuminate\Support\Facades\Log;

/**
 * Asks the DDI engine whether any prescribed drug is contraindicated for the
 * patient's recorded chronic conditions (DrugCentral regulatory lookup).
 *
 * Failure here must never block a prescription: an unreachable DDI service is
 * an infrastructure problem, not a clinical verdict, so we degrade to
 * "no warnings, available:false" and log rather than throwing.
 *
 * Note: this delegates to DdiService/DdiClient (shared transport → correct
 * host, retries, typed exceptions, localized messages) instead of calling
 * Http directly, so it always targets the same DDI service as every other
 * check and never diverges on host/port config.
 */
class ConditionCheckService
{
    public function __construct(
        protected DdiService $ddi
    ) {}

    /**
     * @param  array<int, string>  $medications
     * @param  array<int, string>  $conditions   DrugCentral-standard names
     * @return array{warnings: array<int, array<string, mixed>>, safe: bool, available: bool}
     */
    public function check(array $medications, array $conditions): array
    {
        $medications = array_values(array_filter($medications, fn ($m) => is_string($m) && trim($m) !== ''));
        $conditions = array_values(array_filter($conditions, fn ($c) => is_string($c) && trim($c) !== ''));

        if ($medications === [] || $conditions === []) {
            return ['warnings' => [], 'safe' => true, 'available' => true];
        }

        try {
            $result = $this->ddi->checkConditionContraindications($medications, $conditions);

            return [
                'warnings' => $result['warnings'] ?? [],
                'safe' => $result['safe'] ?? (($result['warnings'] ?? []) === []),
                'available' => true,
            ];
        } catch (AIServiceException $e) {
            Log::warning('Condition check unavailable — degrading to no warnings', [
                'error' => $e->getMessage(),
                'medications' => $medications,
            ]);

            return ['warnings' => [], 'safe' => true, 'available' => false];
        }
    }
}
