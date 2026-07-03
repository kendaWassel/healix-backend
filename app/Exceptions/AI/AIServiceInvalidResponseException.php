<?php

namespace App\Exceptions\AI;

class AIServiceInvalidResponseException extends AIServiceException
{
    public function __construct(string $message = 'AI service returned an invalid response.')
    {
        parent::__construct($message, 502);
    }
}
