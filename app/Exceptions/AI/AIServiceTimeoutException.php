<?php

namespace App\Exceptions\AI;

class AIServiceTimeoutException extends AIServiceException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('ai.service_timeout'), 504);
    }
}
