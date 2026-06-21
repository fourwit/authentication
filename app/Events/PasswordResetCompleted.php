<?php

namespace Modules\Authentication\Events;

class PasswordResetCompleted
{
    public function __construct(public $user = null, public string $source = 'web') {}
}
