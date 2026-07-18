<?php

namespace Modules\Authentication\Events;

use Modules\Authentication\DTOs\Events\EmailVerifiedPayload;

class EmailVerified
{
    public function __construct(public readonly EmailVerifiedPayload $payload) {}
}
