<?php

namespace Modules\Authentication\Events;

use Modules\Authentication\DTOs\Events\UserLoggedOutPayload;

class UserLoggedOut
{
    public function __construct(public readonly UserLoggedOutPayload $payload) {}
}
