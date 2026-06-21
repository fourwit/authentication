<?php

namespace Modules\Authentication\Events;

class VerificationFailed
{
    public function __construct(
        public $user,
        public string $channel,
        public string $source = 'web',
        public ?int $attempts = null
    ) {}
}
