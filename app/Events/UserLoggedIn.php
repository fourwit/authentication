<?php

namespace Modules\Authentication\Events;

class UserLoggedIn
{
    public function __construct(public $user, public string $source = 'web') {}
}
