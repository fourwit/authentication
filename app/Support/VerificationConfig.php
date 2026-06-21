<?php

namespace Modules\Authentication\Support;

use Modules\Authentication\Exceptions\PhoneVerificationNotConfiguredException;

class VerificationConfig
{
    public static function enabled(): bool
    {
        return (bool) config('authentication.verification.enabled', true);
    }

    public static function method(): string
    {
        return (string) config('authentication.verification.method', 'code');
    }

    public static function channel(): string
    {
        $channel = (string) config('authentication.verification.channel', 'email');

        return in_array($channel, ['email', 'phone'], true) ? $channel : 'email';
    }

    public static function ensureSupportedChannel(): string
    {
        $channel = static::channel();

        if ($channel === 'phone') {
            throw new PhoneVerificationNotConfiguredException('Phone verification is not wired yet. Please use email verification.');
        }

        return $channel;
    }

    public static function registrationRequiresVerification(?string $authMethod): bool
    {
        return in_array($authMethod, ['email_otp', 'phone_otp'], true) || static::enabled();
    }
}
