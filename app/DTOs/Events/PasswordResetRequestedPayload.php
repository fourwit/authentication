<?php

namespace Modules\Authentication\DTOs\Events;

use Modules\Authentication\Support\AuthenticationEventSubject;

readonly class PasswordResetRequestedPayload
{
    public function __construct(
        public string $identifier,
        public string $source,
        public string $occurredAt,
    ) {}

    public static function fromIdentifier(string $identifier, string $source): self
    {
        return new self(
            identifier: $identifier,
            source: $source,
            occurredAt: AuthenticationEventSubject::occurredAt(),
        );
    }
}
