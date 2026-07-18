<?php

namespace Modules\Authentication\DTOs\Events;

use Modules\Authentication\Support\AuthenticationEventSubject;

readonly class UserRegisteredPayload
{
    public function __construct(
        public int $userId,
        public ?string $email,
        public string $authMethod,
        public string $source,
        public string $occurredAt,
    ) {}

    public static function fromRegistration(object $user, string $authMethod, string $source): self
    {
        return new self(
            userId: (int) AuthenticationEventSubject::userId($user),
            email: AuthenticationEventSubject::email($user),
            authMethod: $authMethod,
            source: $source,
            occurredAt: AuthenticationEventSubject::occurredAt(),
        );
    }
}
