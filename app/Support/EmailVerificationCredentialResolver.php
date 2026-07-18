<?php

namespace Modules\Authentication\Support;

use Modules\Authentication\DTOs\EmailVerificationData;
use Modules\Identity\Facades\Identity;

class EmailVerificationCredentialResolver
{
    public static function resolveUser(EmailVerificationData $data): mixed
    {
        $user = ($data->phone ? IdentityUserLookup::findByPhone($data->phone) : Identity::findByEmail((string) $data->email))
            ?? ($data->id ? Identity::findById((int) $data->id) : null);

        return $user;
    }

    public static function identifier(EmailVerificationData $data): string
    {
        return $data->email ?? $data->phone ?? '';
    }
}
