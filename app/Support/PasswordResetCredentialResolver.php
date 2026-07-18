<?php

namespace Modules\Authentication\Support;

use Modules\Identity\Facades\Identity;

class PasswordResetCredentialResolver
{
    public static function channelFor(string $authMethod): string
    {
        return $authMethod === 'phone_otp' ? 'phone' : 'email';
    }

    public static function identifierFor(array $data, string $authMethod): ?string
    {
        return $authMethod === 'phone_otp'
            ? ($data['phone'] ?? null)
            : ($data['email'] ?? null);
    }

    public static function resolveUser(?string $identifier, string $authMethod): mixed
    {
        if (! $identifier) {
            return null;
        }

        return $authMethod === 'phone_otp'
            ? IdentityUserLookup::findByPhone($identifier)
            : Identity::findByEmail((string) $identifier);
    }
}
