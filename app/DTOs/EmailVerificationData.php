<?php

namespace Modules\Authentication\DTOs;

use Modules\Authentication\Support\PhoneNumberNormalizer;

class EmailVerificationData
{
    public function __construct(
        public ?string $email,
        public ?string $phone = null,
        public ?string $token = null,
        public ?string $id = null,
        public ?string $hash = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null,
            phone: PhoneNumberNormalizer::normalize($data['phone'] ?? null),
            token: $data['token'] ?? null,
            id: $data['id'] ?? null,
            hash: $data['hash'] ?? null,
        );
    }
}
