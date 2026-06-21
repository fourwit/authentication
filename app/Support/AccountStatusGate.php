<?php

namespace Modules\Authentication\Support;

use Modules\Authentication\Exceptions\InactiveAccountException;
use Modules\Authentication\Exceptions\SuspendedAccountException;
use Modules\Identity\Enums\UserStatus;

class AccountStatusGate
{
    public static function statusOf(mixed $user): ?string
    {
        $status = $user?->status;

        if ($status instanceof UserStatus) {
            return $status->value;
        }

        return is_string($status) ? strtolower($status) : null;
    }

    public static function allowLogin(mixed $user): void
    {
        $status = self::statusOf($user);

        if ($status === UserStatus::SUSPENDED->value) {
            throw new SuspendedAccountException();
        }

        if ($status === UserStatus::INACTIVE->value) {
            throw new InactiveAccountException();
        }
    }

    public static function allowPasswordReset(mixed $user): void
    {
        self::allowLogin($user);
    }

    public static function isPending(mixed $user): bool
    {
        return self::statusOf($user) === UserStatus::PENDING->value;
    }
}
