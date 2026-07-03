<?php

namespace App\Exceptions\AI;

use Exception;

class AudioStorageException extends Exception
{
    public function __construct(string $message = 'Failed to store audio file.')
    {
        parent::__construct($message, 500);
    }
}
