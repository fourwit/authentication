<?php

namespace Modules\Authentication\Events;

class PasswordResetRequested
{
    public function __construct(public string $email, public string $source = 'web') {}
}
