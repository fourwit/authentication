<?php

namespace Modules\Authentication\Events;

class AccountLocked
{
    public function __construct(public string $email, public string $source = 'web') {}
}
