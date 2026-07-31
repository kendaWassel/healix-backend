<?php

namespace App\Services\MedicalAssistant;

use App\Services\AI\FastApiClient;

/**
 * The single HTTP client for the unified Medical Assistant AI microservice
 * (Whisper speech-to-text + MARBERT extraction + Interview Engine + LLM).
 *
 * Reuses the FastApiClient transport (timeouts, retries, logging, typed
 * exceptions) and points it at the services.medical_assistant configuration.
 * There is exactly ONE Medical-Assistant URL — no separate interview/ai clients.
 */
class MedicalAssistantClient extends FastApiClient
{
    protected string $configKey = 'medical_assistant';

    protected string $serviceLabel = 'Medical Assistant service';

    protected string $serviceLabelKey = 'ai.service_label_medical_assistant';
}
