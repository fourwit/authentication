<?php

namespace Modules\Authentication\Events;

use Modules\Authentication\DTOs\Events\PasswordResetRequestedPayload;

class PasswordResetRequested
{
    public function __construct(public readonly PasswordResetRequestedPayload $payload) {}
}
