<?php

namespace App\Exceptions\AI;

use Exception;

class AIServiceException extends Exception
{
    /**
     * $message is nullable rather than carrying a literal default because PHP
     * forbids function calls (here __()) in default parameter values, and the
     * fallback text must be resolved against the current request locale.
     */
    public function __construct(
        ?string $message = null,
        int $code = 502,
        ?Exception $previous = null
    ) {
        parent::__construct($message ?? __('ai.service_failed'), $code, $previous);
    }
}
