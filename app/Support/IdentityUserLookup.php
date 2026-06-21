<?php

namespace Modules\Authentication\Support;

use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Facades\Identity;

class IdentityUserLookup
{
    public static function findByPhone(?string $phone): ?Model
    {
        $phone = PhoneNumberNormalizer::normalize($phone);

        if ($phone === null) {
            return null;
        }

        $user = Identity::userQuery()
            ->whereHas('identityProfile', static function ($query) use ($phone): void {
                $query->where('phone', $phone);
            })
            ->first();

        return $user ? Identity::findById((int) $user->getKey()) : null;
    }
}
