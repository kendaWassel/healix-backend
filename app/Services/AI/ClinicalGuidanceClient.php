<?php

namespace App\Services\AI;

/**
 * HTTP client for the Medical Knowledge & Clinical Guidance FastAPI service.
 *
 * Reuses the FastApiClient transport (timeouts, retries, logging, typed
 * exceptions) and only repoints it at the services.clinical_guidance config.
 * The service runs on an internal port and is never exposed to the internet;
 * Laravel is the sole authenticated door (same posture as the DDI/Lab services).
 */
class ClinicalGuidanceClient extends FastApiClient
{
    protected string $configKey = 'clinical_guidance';

    protected string $serviceLabel = 'Clinical Guidance service';

    protected string $serviceLabelKey = 'ai.service_label_clinical_guidance';
}
