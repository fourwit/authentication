<?php

namespace Modules\Authentication\Exceptions;

use RuntimeException;

class InvalidVerificationTokenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid verification token.');
    }
}
