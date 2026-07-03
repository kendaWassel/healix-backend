<?php

namespace App\Exceptions\AI;

class AIServiceUnavailableException extends AIServiceException
{
    public function __construct(string $message = 'AI service is unavailable.')
    {
        parent::__construct($message, 503);
    }
}
