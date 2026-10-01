<?php

namespace App\Services\Integration;

use RuntimeException;

class IntegrationException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 422)
    {
        parent::__construct($message);
    }
}
