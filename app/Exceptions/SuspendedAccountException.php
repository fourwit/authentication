<?php

namespace Modules\Authentication\Exceptions;

use RuntimeException;

class SuspendedAccountException extends RuntimeException
{
    public function __construct(string $message = 'Your account is suspended. Please contact support.')
    {
        parent::__construct($message);
    }
}
