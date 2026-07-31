<?php

namespace App\Exceptions\AI;

class AIServiceUnavailableException extends AIServiceException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('ai.service_unavailable'), 503);
    }
}
