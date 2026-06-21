<?php

namespace Modules\Authentication\Support;

class PasswordResetMethodResolver
{
    public static function resolve(?string $requestedMethod): string
    {
        $methods = static::methods();
        $default = (string) config('authentication.password_reset.default_method', 'link');

        if ($requestedMethod && in_array($requestedMethod, $methods, true)) {
            return $requestedMethod;
        }

        if (in_array($default, $methods, true)) {
            return $default;
        }

        return $methods[0] ?? 'link';
    }

    public static function methods(): array
    {
        $methods = (array) config('authentication.password_reset.methods', []);
        $allowedChannels = (array) config('authentication.password_reset.allowed_channels', ['email', 'phone']);

        return array_values(array_filter($methods, static function (string $method) use ($allowedChannels): bool {
            if (! PhoneInputConfig::supportsPhoneMethod($method)) {
                return false;
            }

            if ($method === 'phone_otp' && ! in_array('phone', $allowedChannels, true)) {
                return false;
            }

            if ($method === 'email_otp' && ! in_array('email', $allowedChannels, true)) {
                return false;
            }

            if ($method === 'link' && ! in_array('email', $allowedChannels, true)) {
                return false;
            }

            return true;
        }));
    }

    public static function fields(string $method): array
    {
        return (array) config("authentication.password_reset.fields_per_method.{$method}", []);
    }

    public static function requiredFields(string $method): array
    {
        return array_keys(array_filter(
            static::fields($method),
            static fn (mixed $metadata): bool => (bool) data_get($metadata, 'required', false)
        ));
    }
}
