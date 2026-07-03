<?php

namespace App\Exceptions\AI;

use Exception;

class AIServiceException extends Exception
{
    public function __construct(
        string $message = 'AI service request failed.',
        int $code = 502,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
