<?php

namespace App\Services\AI;

use App\Exceptions\AI\AIServiceException;
use App\Exceptions\AI\AIServiceInvalidResponseException;
use App\Services\MedicalAssistant\MedicalAssistantClient;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over the Medical Assistant service for Whisper speech-to-text.
 *
 * Talks to the single unified Medical Assistant microservice via
 * MedicalAssistantClient. (Symptom extraction and the interview turn are owned
 * by the Interview pipeline, not here.)
 */
class AIService
{
    public function __construct(
        protected MedicalAssistantClient $client
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
            throw new AIServiceException(__('ai.speech_failed'));
        }

        $text = $response['text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            throw new AIServiceInvalidResponseException(__('ai.speech_no_text'));
        }

        Log::info('Speech-to-text finished', [
            'audio_path' => $audioPath,
            'text_length' => strlen($text),
        ]);

        return $text;
    }
}
