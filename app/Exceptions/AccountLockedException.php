<?php

namespace Modules\Authentication\Exceptions;

use RuntimeException;

class AccountLockedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Account is locked.');
    }
}
