<?php

namespace Modules\Authentication\DTOs;

class ResetPasswordData
{
    public function __construct(
        public string $authMethod,
        public ?string $token,
        public ?string $email,
        public ?string $phone,
        public string $password,
        public ?string $passwordConfirmation = null,
        public ?string $resetGrant = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            authMethod: (string) ($data['auth_method'] ?? 'link'),
            token: isset($data['token']) && $data['token'] !== '' ? (string) $data['token'] : null,
            email: isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null,
            phone: isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null,
            password: (string) ($data['password'] ?? ''),
            passwordConfirmation: $data['password_confirmation'] ?? null,
            resetGrant: isset($data['reset_grant']) && $data['reset_grant'] !== '' ? (string) $data['reset_grant'] : null,
        );
    }
}
