<?php

namespace Modules\Authentication\Events;

class VerificationCodeSent
{
    public function __construct(
        public $user,
        public string $channel,
        public string $destination,
        public string $source = 'web'
    ) {}
}
