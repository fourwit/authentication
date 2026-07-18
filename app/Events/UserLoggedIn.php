<?php

namespace Modules\Authentication\Events;

use Modules\Authentication\DTOs\Events\UserLoggedInPayload;

class UserLoggedIn
{
    public function __construct(public readonly UserLoggedInPayload $payload) {}
}
