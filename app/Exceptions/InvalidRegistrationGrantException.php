<?php

namespace Modules\Authentication\Exceptions;

use RuntimeException;

class InvalidRegistrationGrantException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid or expired registration grant.');
    }
}
