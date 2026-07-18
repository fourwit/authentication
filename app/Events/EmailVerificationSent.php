<?php

namespace Modules\Authentication\Events;

use Modules\Authentication\DTOs\Events\EmailVerificationSentPayload;

class EmailVerificationSent
{
    public function __construct(public readonly EmailVerificationSentPayload $payload) {}
}
