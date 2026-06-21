<?php

namespace Modules\Authentication\Events;

class EmailVerificationSent
{
    public function __construct(public string $email, public string $source = 'web') {}
}
