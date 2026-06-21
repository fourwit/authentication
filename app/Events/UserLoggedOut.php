<?php

namespace Modules\Authentication\Events;

class UserLoggedOut
{
    public function __construct(public $user = null, public string $source = 'web') {}
}
