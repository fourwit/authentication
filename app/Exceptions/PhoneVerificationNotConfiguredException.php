<?php

namespace Modules\Authentication\Exceptions;

use Exception;

class PhoneVerificationNotConfiguredException extends Exception
{
    public function __construct(string $message = 'Phone verification is enabled but no PhoneVerificationCodeSenderInterface implementation is bound. Please configure a phone/SMS sender.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
