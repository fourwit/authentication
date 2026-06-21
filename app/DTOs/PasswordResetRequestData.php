<?php

namespace Modules\Authentication\DTOs;

use Modules\Authentication\Support\PhoneNumberNormalizer;

class PasswordResetRequestData
{
    public function __construct(
        public string $authMethod,
        public ?string $email,
        public ?string $phone = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            authMethod: (string) ($data['auth_method'] ?? 'link'),
            email: isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null,
            phone: PhoneNumberNormalizer::normalize($data['phone'] ?? null),
        );
    }
}
