<?php

namespace Modules\Authentication\Exceptions;

use RuntimeException;

class InvalidPasswordResetTokenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid password reset token.');
    }
}
