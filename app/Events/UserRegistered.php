<?php

namespace Modules\Authentication\Events;

use Modules\Authentication\DTOs\Events\UserRegisteredPayload;

class UserRegistered
{
    public function __construct(public readonly UserRegisteredPayload $payload) {}
}
