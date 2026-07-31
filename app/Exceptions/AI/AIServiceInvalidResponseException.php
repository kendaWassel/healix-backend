<?php

namespace App\Exceptions\AI;

class AIServiceInvalidResponseException extends AIServiceException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('ai.service_invalid_response'), 502);
    }
}
