<?php

namespace Modules\Authentication\DTOs;

use Modules\Authentication\Support\PhoneNumberNormalizer;

class RegisterUserData
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $password = null,
        public ?string $username = null,
        public ?string $phone = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public string $authMethod = 'email_password',
        public ?string $passwordConfirmation = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
            password: isset($data['password']) ? (string) $data['password'] : null,
            username: isset($data['username']) ? (string) $data['username'] : null,
            phone: PhoneNumberNormalizer::normalize($data['phone'] ?? null),
            firstName: isset($data['first_name']) ? (string) $data['first_name'] : null,
            lastName: isset($data['last_name']) ? (string) $data['last_name'] : null,
            authMethod: (string) ($data['auth_method'] ?? 'email_password'),
            passwordConfirmation: $data['password_confirmation'] ?? null,
        );
    }
}
