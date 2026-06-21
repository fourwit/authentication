<?php

namespace Modules\Authentication\Exceptions;

use RuntimeException;

class EmailNotVerifiedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Email is not verified.');
    }
}
