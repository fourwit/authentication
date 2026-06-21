<?php

namespace Modules\Authentication\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface VerificationNotifierInterface
{
    public function send(Authenticatable $user, string $channel, string $destination, string $plainCode, string $source = 'web'): void;
}
