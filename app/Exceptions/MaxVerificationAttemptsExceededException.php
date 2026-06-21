<?php

namespace Modules\Authentication\Exceptions;

use Exception;

class MaxVerificationAttemptsExceededException extends Exception
{
    public function __construct(string $message = 'Too many verification attempts. Please request a new code.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
