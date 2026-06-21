<?php

namespace Modules\Authentication\DTOs;

use Modules\Authentication\Support\PhoneNumberNormalizer;

class LoginData
{
    public function __construct(
        public ?string $email,
        public ?string $phone,
        public ?string $password,
        public string $authMethod = 'email_password',
        public bool $remember = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null,
            phone: PhoneNumberNormalizer::normalize($data['phone'] ?? null),
            password: isset($data['password']) && $data['password'] !== '' ? (string) $data['password'] : null,
            authMethod: (string) ($data['auth_method'] ?? 'email_password'),
            remember: (bool) ($data['remember'] ?? false),
        );
    }
}
