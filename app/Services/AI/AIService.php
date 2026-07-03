<?php

namespace App\Services\AI;

use App\Exceptions\AI\AIServiceException;
use App\Exceptions\AI\AIServiceInvalidResponseException;
use Illuminate\Support\Facades\Log;

class AIService
{
    public function __construct(
        protected FastApiClient $client
    ) {}

    /**
     * @throws AIServiceException
     */
    public function speechToText(string $audioPath): string
    {
        Log::info('Speech-to-text started', [
            'audio_path' => $audioPath,
        ]);

        // Laravel and FastAPI run on the same machine and share a filesystem, so
        // audio_path avoids an unnecessary network hop. FastAPI's SpeechToTextRequest
        // schema rejects a request that sets both audio_path and audio_url — send
        // exactly one field.
        $response = $this->client->post('/api/speech-to-text', [
            'audio_path' => $audioPath,
        ]);

        if (! ($response['success'] ?? false)) {
            throw new AIServiceException('Speech-to-text conversion failed.');
        }

        $text = $response['text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            throw new AIServiceInvalidResponseException('AI service did not return transcribed text.');
        }

        Log::info('Speech-to-text finished', [
            'audio_path' => $audioPath,
            'text_length' => strlen($text),
        ]);

        return $text;
    }

    /**
     * @return array<int, string>
     *
     * @throws AIServiceException
     */
    public function extractSymptoms(string $text): array
    {
        Log::info('Symptom extraction started', [
            'text_length' => strlen($text),
        ]);

        $response = $this->client->post('/api/symptoms/extract', [
            'text' => $text,
        ]);

        if (! ($response['success'] ?? false)) {
            throw new AIServiceException('Symptom extraction failed.');
        }

        $symptoms = $response['detected_symptoms'] ?? null;

        if (! is_array($symptoms)) {
            throw new AIServiceInvalidResponseException('AI service did not return detected symptoms.');
        }

        $symptoms = array_values(array_filter($symptoms, fn ($symptom) => is_string($symptom) && $symptom !== ''));

        Log::info('Symptom extraction finished', [
            'symptom_count' => count($symptoms),
        ]);

        return $symptoms;
    }

    /**
     * Integration point for the FastAPI Medical Assistant.
     *
     * When the endpoint is wired up, this method should send the patient message
     * to the AI service and return the assistant reply. Returns null when no
     * response is available yet so callers can skip persisting an assistant message.
     */
    public function getMedicalAssistantResponse(string $text, int $conversationId): ?string
    {
        Log::info('Medical assistant integration point reached', [
            'conversation_id' => $conversationId,
            'text_length' => strlen($text),
        ]);

        // TODO: POST to the FastAPI medical assistant endpoint when available.
        // Example:
        // $response = $this->client->post('/api/medical-assistant/chat', [
        //     'conversation_id' => $conversationId,
        //     'text' => $text,
        // ]);
        //
        // return $response['reply'] ?? null;

        return null;
    }
}
