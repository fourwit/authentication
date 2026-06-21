<?php

namespace Modules\Authentication\Events;

class UserRegistered
{
    public function __construct(public $user, public string $source = 'web') {}
}
