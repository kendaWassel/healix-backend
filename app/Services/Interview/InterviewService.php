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
     * @return array{session_id: string, finished: bool, question: ?string, next_slot: ?string, symptoms: array<int, array{text: string, negated: bool}>}
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
            throw new AIServiceInvalidResponseException('Interview service did not return a valid "finished" flag.');
        }

        $returnedSessionId = $response['session_id'] ?? $sessionId;

        if (! is_string($returnedSessionId) || $returnedSessionId === '') {
            throw new AIServiceInvalidResponseException('Interview service did not return a session id.');
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
        ]);

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
        ];
    }
}
