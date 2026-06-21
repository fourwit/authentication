<?php

namespace Modules\Authentication\Events;

class FailedLoginRecorded
{
    public function __construct(public string $email, public string $source = 'web') {}
}
