<?php

namespace Modules\Authentication\Events;

use Modules\Authentication\DTOs\Events\PasswordResetCompletedPayload;

class PasswordResetCompleted
{
    public function __construct(public readonly PasswordResetCompletedPayload $payload) {}
}
