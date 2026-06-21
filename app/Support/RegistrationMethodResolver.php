<?php

namespace Modules\Authentication\Support;

class RegistrationMethodResolver
{
    public static function resolve(?string $requestedMethod): string
    {
        $methods = static::methods();
        $default = (string) config('authentication.registration.default_method', 'email_password');

        if ($requestedMethod && in_array($requestedMethod, $methods, true)) {
            return $requestedMethod;
        }

        if (in_array($default, $methods, true)) {
            return $default;
        }

        return $methods[0] ?? 'email_password';
    }

    public static function methods(): array
    {
        $methods = (array) config('authentication.registration.methods', []);

        return array_values(array_filter($methods, static fn (string $method): bool => PhoneInputConfig::supportsPhoneMethod($method)));
    }

    public static function fields(string $method): array
    {
        return (array) config("authentication.registration.fields_per_method.{$method}", []);
    }

    public static function requiredFields(string $method): array
    {
        return array_keys(array_filter(
            static::fields($method),
            static fn (mixed $metadata): bool => (bool) data_get($metadata, 'required', false)
        ));
    }

    public static function optionalFields(string $method): array
    {
        return array_keys(array_filter(
            static::fields($method),
            static fn (mixed $metadata): bool => ! (bool) data_get($metadata, 'required', false)
        ));
    }
}
