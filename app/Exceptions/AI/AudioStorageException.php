<?php

namespace App\Exceptions\AI;

use Exception;

class AudioStorageException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('ai.speech_storage_failed'), 500);
    }
}
