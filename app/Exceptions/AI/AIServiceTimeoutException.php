<?php

namespace App\Exceptions\AI;

class AIServiceTimeoutException extends AIServiceException
{
    public function __construct(string $message = 'AI service request timed out.')
    {
        parent::__construct($message, 504);
    }
}
