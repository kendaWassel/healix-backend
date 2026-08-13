<?php

namespace App\Services\Healix;

use App\Services\AI\FastApiClient;

/**
 * HTTP client for the Healix AI (Arabic symptom-triage) FastAPI microservice.
 *
 * Reuses the FastApiClient transport (timeouts, retries, logging, typed
 * exceptions) and only repoints it at the services.healix configuration —
 * same pattern as LabClient/DdiClient/ClinicalGuidanceClient. The one
 * difference from those: this service is not internet-facing and gates
 * every route but /health on a shared-secret header (its own CLAUDE.md >
 * Architecture: "Internal network + shared secret in header"), so
 * $apiKeyHeader is overridden to the exact header name that service's
 * POST /chat checks — api/main.py's own _INTERNAL_TOKEN_HEADER constant.
 * FastApiClient::buildRequest() already attaches it automatically whenever
 * services.healix.api_key is set, the same mechanism LabClient etc. use
 * for their own (currently unused) X-API-KEY.
 */
class HealixAiClient extends FastApiClient
{
    protected string $configKey = 'healix';

    protected string $serviceLabel = 'Healix AI triage service';

    protected string $serviceLabelKey = 'ai.service_label_healix';

    protected string $apiKeyHeader = 'X-Healix-Internal-Token';

    /**
     * POST /chat — one triage turn. Field names match api/contracts.py's
     * ChatRequest exactly (thread_id, message, patient_sex,
     * medical_record_summary) — this method does not rename or restructure
     * them. patient_sex/medical_record_summary are omitted from the payload
     * entirely when null, the same "build the field only when a value
     * exists" convention LabAnalysisService::buildPatientFields() already
     * uses for its own optional fields, rather than sending an explicit
     * JSON null (which the Python side's Pydantic model would accept
     * identically, but omitting keeps the request body honest about what
     * Laravel actually knows this turn).
     *
     * @throws \App\Exceptions\AI\AIServiceException
     */
    public function sendMessage(
        string $threadId,
        string $message,
        ?string $patientSex = null,
        ?string $medicalRecordSummary = null,
    ): array {
        $payload = [
            'thread_id' => $threadId,
            'message' => $message,
        ];

        if ($patientSex !== null) {
            $payload['patient_sex'] = $patientSex;
        }

        if ($medicalRecordSummary !== null) {
            $payload['medical_record_summary'] = $medicalRecordSummary;
        }

        return $this->post('/chat', $payload);
    }

    /**
     * GET /health — deliberately NOT /api/health: unlike the Lab service
     * (LabAnalysisService::health() calls '/api/health'), the Healix AI
     * service's own FastAPI app (api/main.py) mounts its routes at the
     * root, not under /api — confirmed directly against that file rather
     * than assumed from the Lab client's path.
     *
     * @throws \App\Exceptions\AI\AIServiceException
     */
    public function health(): array
    {
        return $this->get('/health');
    }
}
