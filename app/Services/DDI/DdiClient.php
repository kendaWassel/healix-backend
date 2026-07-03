<?php

namespace App\Services\DDI;

use App\Services\AI\FastApiClient;

/**
 * HTTP client for the DDI (drug-drug interaction) FastAPI microservice.
 *
 * Reuses the FastApiClient transport (timeouts, retries, logging, typed
 * exceptions) and only repoints it at the services.ddi configuration.
 */
class DdiClient extends FastApiClient
{
    protected string $configKey = 'ddi';

    protected string $serviceLabel = 'DDI service';
}
