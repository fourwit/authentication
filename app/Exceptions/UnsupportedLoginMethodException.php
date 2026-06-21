<?php

namespace Modules\Authentication\Exceptions;

use RuntimeException;

class UnsupportedLoginMethodException extends RuntimeException
{
    public function __construct(string $message = 'This login method is not implemented yet.')
    {
        parent::__construct($message);
    }
}
