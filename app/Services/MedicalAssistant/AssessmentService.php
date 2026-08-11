<?php

namespace App\Services\MedicalAssistant;

use App\Exceptions\AI\AIServiceException;
use App\Exceptions\AI\AIServiceInvalidResponseException;
use Illuminate\Support\Facades\Log;

/**
 * Application service for the post-interview clinical assessment.
 *
 * Talks to the unified Medical Assistant microservice (POST /api/assessment/run)
 * through the shared MedicalAssistantClient. Given a finished interview's raw
 * patient messages and collected symptoms, it returns urgency, specialty,
 * confidence and an Arabic explanation. Stateless — the Python endpoint does
 * not read the interview engine's session, it takes a full snapshot per call.
 */
class AssessmentService
{
    public function __construct(
        protected MedicalAssistantClient $client
    ) {}

    /**
     * @param  array<int, string>  $rawMessages  Patient messages, in turn order.
     * @param  array<int, array{text: string, negated: bool, confidence: float|null}>  $symptoms
     * @param  array{emergency_detected: bool, risk_level: string}|null  $interviewRisk  Red-flag
     *     verdict the interview engine already computed (InterviewService::sendMessage()'s
     *     emergency_detected/risk_level), forwarded verbatim as AssessmentRequest.interview_risk
     *     so the Python side doesn't re-run RedFlagEngine on raw_messages itself. Optional: when
     *     null (no interview call preceded this, or the caller doesn't have it), the field is
     *     simply omitted from the request body — the Python endpoint's own same-process
     *     RedFlagEngine fallback already covers that case, so this is a latency/cost
     *     optimization, not a correctness requirement. No new red-flag logic here.
     * @return array{
     *     urgency: array{level: string, score: float, explanation: string},
     *     specialty: array{specialty: string, confidence: float, explanation: string},
     *     confidence: array{overall_confidence: float, requires_human_review: bool, explanation: string},
     *     explanation: array{summary: string, medical_reasoning: string, recommendation: string, disclaimer: string},
     *     predictions: array<int, array{disease: string, score: float, explanation: string}>
     * }
     *
     * @throws AIServiceException
     */
    public function run(string $sessionId, array $rawMessages, array $symptoms, ?array $interviewRisk = null): array
    {
        Log::info('Assessment run started', [
            'session_id' => $sessionId,
            'message_count' => count($rawMessages),
            'symptom_count' => count($symptoms),
            'interview_risk_supplied' => $interviewRisk !== null,
        ]);

        $payload = [
            'session_id' => $sessionId,
            'raw_messages' => $rawMessages,
            'symptoms' => array_map(static fn (array $s) => [
                'text' => $s['text'],
                'negated' => $s['negated'],
                'confidence' => $s['confidence'] ?? 1.0,
            ], $symptoms),
        ];

        if ($interviewRisk !== null) {
            $payload['interview_risk'] = [
                'emergency_detected' => (bool) ($interviewRisk['emergency_detected'] ?? false),
                'risk_level' => (string) ($interviewRisk['risk_level'] ?? 'none'),
            ];
        }

        $response = $this->client->post('/api/assessment/run', $payload);

        foreach (['urgency', 'specialty', 'confidence', 'explanation'] as $key) {
            if (! isset($response[$key]) || ! is_array($response[$key])) {
                throw new AIServiceInvalidResponseException(__('ai.assessment_invalid_response'));
            }
        }

        Log::info('Assessment run finished', [
            'session_id' => $sessionId,
            'urgency_level' => $response['urgency']['level'] ?? null,
        ]);

        return [
            'urgency' => $response['urgency'],
            'specialty' => $response['specialty'],
            'confidence' => $response['confidence'],
            'explanation' => $response['explanation'],
            'predictions' => $response['predictions']['predictions'] ?? [],
        ];
    }
}
