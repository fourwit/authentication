<?php

namespace Modules\Authentication\Support;

use Modules\Authentication\DTOs\LoginData;
use Modules\Identity\Facades\Identity;

class LoginCredentialResolver
{
    public static function identifier(LoginData $data): string
    {
        return $data->email ?? $data->phone ?? '';
    }

    public static function channelFor(string $authMethod): string
    {
        return $authMethod === 'phone_otp' ? 'phone' : 'email';
    }

    public static function resolveUser(LoginData $data): mixed
    {
        return $data->phone
            ? IdentityUserLookup::findByPhone($data->phone)
            : Identity::findByEmail((string) $data->email);
    }
}
