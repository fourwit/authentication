<?php

namespace Modules\Authentication\Events;

class EmailVerified
{
    public function __construct(public $user = null, public string $source = 'web') {}
}
