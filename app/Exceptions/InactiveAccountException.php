<?php

namespace Modules\Authentication\Exceptions;

use RuntimeException;

class InactiveAccountException extends RuntimeException
{
    public function __construct(string $message = 'This account is inactive. Please contact support.')
    {
        parent::__construct($message);
    }
}
