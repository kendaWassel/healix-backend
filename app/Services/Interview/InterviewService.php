<?php

namespace App\Services\Interview;

use App\Exceptions\AI\AIServiceException;
use App\Exceptions\AI\AIServiceInvalidResponseException;
use App\Services\MedicalAssistant\MedicalAssistantClient;
use Illuminate\Support\Facades\Log;

/**
 * Application service for the medical history-taking interview.
 *
 * Talks to the unified Medical Assistant microservice (POST /api/interview/turn)
 * through the shared MedicalAssistantClient. The service is treated as a black
 * box: given a patient message and an optional session id, it returns the next
 * single Arabic question or a "finished" signal. This class owns all
 * request/response handling so the controller stays thin.
 */
class InterviewService
{
    public function __construct(
        protected MedicalAssistantClient $client
    ) {}

    /**
     * Send one patient message and return the assistant's turn decision.
     *
     * @return array{
     *     session_id: string,
     *     finished: bool,
     *     question: ?string,
     *     next_slot: ?string,
     *     symptoms: array<int, array{text: string, negated: bool, confidence: float|null}>,
     *     emergency_detected: bool,
     *     risk_level: string,
     *     red_flags: array<int, array<string, mixed>>,
     *     recommended_action: ?string,
     *     interview_record: array<string, mixed>|null
     * }
     *
     * @throws AIServiceException
     */
    public function sendMessage(string $text, ?string $sessionId = null): array
    {
        Log::info('Interview turn started', [
            'session_id' => $sessionId,
            'text_length' => strlen($text),
        ]);

        $response = $this->client->post('/api/interview/turn', [
            'text' => $text,
            'session_id' => $sessionId,
        ]);

        if (! array_key_exists('finished', $response) || ! is_bool($response['finished'])) {
            throw new AIServiceInvalidResponseException(__('ai.interview_invalid_finished'));
        }

        $returnedSessionId = $response['session_id'] ?? $sessionId;

        if (! is_string($returnedSessionId) || $returnedSessionId === '') {
            throw new AIServiceInvalidResponseException(__('ai.interview_no_session'));
        }

        $symptoms = [];
        foreach ($response['symptoms'] ?? [] as $symptom) {
            if (is_array($symptom) && isset($symptom['text'])) {
                $symptoms[] = [
                    'text' => (string) $symptom['text'],
                    'negated' => (bool) ($symptom['negated'] ?? false),
                    // The interview endpoint may omit confidence; keep it forward-compatible.
                    'confidence' => isset($symptom['confidence']) && is_numeric($symptom['confidence'])
                        ? (float) $symptom['confidence']
                        : null,
                ];
            }
        }

        Log::info('Interview turn finished', [
            'session_id' => $returnedSessionId,
            'finished' => $response['finished'],
            'emergency_detected' => $response['emergency_detected'] ?? false,
        ]);

        $interviewRecord = self::extractInterviewRecord($response);

        return [
            'session_id' => $returnedSessionId,
            'finished' => $response['finished'],
            'question' => isset($response['question']) && is_string($response['question'])
                ? $response['question']
                : null,
            'next_slot' => isset($response['next_slot']) && is_string($response['next_slot'])
                ? $response['next_slot']
                : null,
            'symptoms' => $symptoms,
            // Safety layer (Python RedFlagEngine, computed deterministically
            // regardless of LLM success) — previously read off $response only
            // for the finished/session checks above, then silently dropped
            // here instead of reaching the caller. Same defensive-parsing
            // style as the fields above: missing/wrong-typed -> safe default,
            // never fabricated.
            'emergency_detected' => isset($response['emergency_detected']) && is_bool($response['emergency_detected'])
                ? $response['emergency_detected']
                : false,
            'risk_level' => isset($response['risk_level']) && is_string($response['risk_level'])
                ? $response['risk_level']
                : 'none',
            'red_flags' => is_array($response['red_flags'] ?? null) ? $response['red_flags'] : [],
            'recommended_action' => isset($response['recommended_action']) && is_string($response['recommended_action'])
                ? $response['recommended_action']
                : null,
            'interview_record' => $interviewRecord,
        ];
    }

    /**
     * Build AssessmentRequest.interview_record from /api/interview/turn fields.
     * Returns null when the response carries no structured record yet.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    protected static function extractInterviewRecord(array $response): ?array
    {
        $record = [
            'chief_complaint' => self::optionalString($response['chief_complaint'] ?? null),
            'severity' => self::optionalString($response['severity'] ?? null),
            'duration' => self::optionalString($response['duration'] ?? null),
            'body_location' => self::optionalString($response['body_location'] ?? null),
            'medications' => self::stringList($response['medications'] ?? []),
            'allergies' => self::stringList($response['allergies'] ?? []),
            'chronic_conditions' => self::stringList($response['chronic_conditions'] ?? []),
            'family_history' => self::stringList($response['family_history'] ?? []),
        ];

        $hasScalar = collect($record)
            ->only(['chief_complaint', 'severity', 'duration', 'body_location'])
            ->contains(fn ($value) => $value !== null);
        $hasLists = collect($record)
            ->only(['medications', 'allergies', 'chronic_conditions', 'family_history'])
            ->contains(fn ($value) => $value !== []);

        return ($hasScalar || $hasLists) ? $record : null;
    }

    protected static function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<int, string>
     */
    protected static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($item) => is_string($item) && $item !== '' ? $item : null, $value),
            static fn ($item) => $item !== null
        ));
    }
}
