<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Module 2: something went wrong while scanning a product photo.
 * The message is written for the Owner and is safe to show on screen.
 * The status is the HTTP status the scan endpoint answers with.
 */
class GeminiVisionException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 503)
    {
        parent::__construct($message);
    }
}
