<?php

namespace App\Services\Lab;

use App\Services\AI\FastApiClient;

/**
 * HTTP client for the LabInsight AI (lab test analysis) FastAPI microservice.
 *
 * Reuses the FastApiClient transport (timeouts, retries, logging, typed
 * exceptions) and only repoints it at the services.lab configuration.
 */
class LabClient extends FastApiClient
{
    protected string $configKey = 'lab';

    protected string $serviceLabel = 'Lab analysis service';
}
