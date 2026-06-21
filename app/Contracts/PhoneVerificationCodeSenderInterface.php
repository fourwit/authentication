<?php

namespace Modules\Authentication\Contracts;

interface PhoneVerificationCodeSenderInterface
{
    public function send(string $phone, string $code, string $source = 'web'): void;
}
