<?php

namespace App\Services\Healix;

use App\Services\AI\FastApiClient;

/**
 * HTTP client for the Healix AI (Arabic symptom-triage) FastAPI microservice.
 *
 * Reuses the FastApiClient transport (timeouts, retries, logging, typed
 * exceptions) and only repoints it at the services.healix configuration —
 * same pattern as LabClient/DdiClient. The one
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
     * Keep only thread_id — drop `message` (the patient's raw Arabic
     * complaint, verbatim) and `medical_record_summary` (still patient-
     * derived, even filtered) before this request payload is logged.
     * `patient_sex` is dropped too rather than special-cased in: this
     * service's own CLAUDE.md treats every request/response field here as
     * patient data unless demonstrably not (see that project's "Audit
     * logs and patient data" section), and thread_id alone is already
     * enough to correlate this log line with the conversation.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function redactPayloadForLogging(array $payload): array
    {
        return ['thread_id' => $payload['thread_id'] ?? null];
    }

    /**
     * Keep only thread_id — drop `reply` (patient-facing triage text),
     * `diagnosis`, and `reports` (full doctor + patient report bodies,
     * CLAUDE.md's own "Audit logs and patient data" section) before a
     * /chat response is logged. status/latency are logged as separate,
     * already-safe top-level keys by FastApiClient::send() itself, not
     * part of this body.
     *
     * @param  array<string, mixed>|string|null  $body
     * @return array<string, mixed>|string|null
     */
    protected function redactResponseBodyForLogging($body)
    {
        return is_array($body) ? ['thread_id' => $body['thread_id'] ?? null] : null;
    }

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
     * POST /speech/transcribe — speech-to-text only, does not itself talk
     * to the graph (api/main.py's own module docstring: "A voice turn is
     * therefore always two calls... never a single combined 'voice chat'
     * endpoint"). The multipart field name sent to Python is 'file' — that
     * service's own `File(...)` parameter name — decoupled on purpose from
     * whatever field name Laravel's own incoming request uses (see
     * HealixSpeechController, which accepts 'audio' to match this
     * project's existing speech-upload convention).
     *
     * @return array{text: string}
     *
     * @throws \App\Exceptions\AI\AIServiceException
     */
    public function transcribe(string $fileContents, string $fileName): array
    {
        return $this->postMultipart('/speech/transcribe', 'file', $fileContents, $fileName);
    }

    /**
     * POST /speech/synthesize — text-to-speech only. Returns raw MP3 bytes
     * (audio/mpeg), not JSON — api/main.py's own route returns a
     * Response(content=audio_bytes, media_type="audio/mpeg"), never a
     * JSON-wrapped payload, so this uses postBinary() rather than post().
     *
     * @throws \App\Exceptions\AI\AIServiceException
     */
    public function synthesize(string $text): string
    {
        return $this->postBinary('/speech/synthesize', ['text' => $text]);
    }

    /**
     * POST /health-questions — general Arabic health-education Q&A. A
     * SEPARATE feature from sendMessage()/POST /chat above: this route
     * never diagnoses and never touches triage state (that Python
     * service's own docs/AHD_DATA_PROVENANCE.md documents the full
     * separation). Field names match
     * api/health_qa_contracts.py's HealthQuestionRequest exactly
     * (question, thread_id, locale).
     *
     * $threadId here is an audit-correlation tag only, not a conversation
     * key — this route has no LangGraph checkpointer behind it
     * (rag/health_education/service.py's answer_health_question() is
     * stateless per call), so reusing the same id across many calls
     * carries none of sendMessage()'s thread_id collision risk.
     *
     * @throws \App\Exceptions\AI\AIServiceException
     */
    public function askHealthQuestion(string $question, ?string $threadId = null, string $locale = 'ar'): array
    {
        $payload = [
            'question' => $question,
            'locale' => $locale,
        ];

        if ($threadId !== null) {
            $payload['thread_id'] = $threadId;
        }

        return $this->post('/health-questions', $payload);
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
