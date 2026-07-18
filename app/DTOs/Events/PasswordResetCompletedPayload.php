<?php

namespace Modules\Authentication\DTOs\Events;

use Modules\Authentication\Support\AuthenticationEventSubject;

readonly class PasswordResetCompletedPayload
{
    public function __construct(
        public ?int $userId,
        public ?string $email,
        public ?string $phone,
        public string $source,
        public string $occurredAt,
    ) {}

    public static function fromUser(mixed $user, string $source): self
    {
        return new self(
            userId: AuthenticationEventSubject::userId($user),
            email: AuthenticationEventSubject::email($user),
            phone: AuthenticationEventSubject::phone($user),
            source: $source,
            occurredAt: AuthenticationEventSubject::occurredAt(),
        );
    }
}
