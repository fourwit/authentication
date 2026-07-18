<?php

namespace Modules\Authentication\DTOs\Events;

use Modules\Authentication\Support\AuthenticationEventSubject;

readonly class EmailVerifiedPayload
{
    public function __construct(
        public int $userId,
        public ?string $email,
        public string $source,
        public string $occurredAt,
    ) {}

    public static function fromUser(object $user, string $source): self
    {
        return new self(
            userId: (int) AuthenticationEventSubject::userId($user),
            email: AuthenticationEventSubject::email($user),
            source: $source,
            occurredAt: AuthenticationEventSubject::occurredAt(),
        );
    }
}
