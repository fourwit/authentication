<?php

namespace Modules\Authentication\Support;

class LoginMethodResolver
{
    public static function resolve(?string $requestedMethod): string
    {
        $methods = static::accessibleMethods();
        $default = static::defaultMethod();

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
        $methods = (array) config('authentication.login.methods', []);

        return array_values(array_filter($methods, static fn (string $method): bool => PhoneInputConfig::supportsPhoneMethod($method)));
    }

    public static function defaultMethod(): string
    {
        return (string) config('authentication.login.default_method', 'email_password');
    }

    public static function accessibleMethods(): array
    {
        $methods = static::methods();
        $default = static::defaultMethod();
        $allowed = [$default];

        foreach (static::alternativeMethods() as $alternativeMethod) {
            $allowed[] = $alternativeMethod;
        }

        return array_values(array_unique(array_values(array_filter(
            $allowed,
            static fn (?string $method): bool => $method !== null && in_array($method, $methods, true)
        ))));
    }

    public static function alternativeMethods(): array
    {
        $alternatives = (array) config('authentication.login.alternative_methods', []);

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => is_string($value) ? trim($value) : '', $alternatives),
            static fn (string $value): bool => in_array($value, ['email_otp', 'phone_otp'], true)
        ));
    }

    public static function visibleAlternativeMethods(): array
    {
        if (! (bool) config('authentication.login.show_alternative_methods', false)) {
            return [];
        }

        return static::alternativeMethods();
    }

    public static function switchLinksFor(string $currentMethod): array
    {
        $links = [];

        foreach (static::visibleAlternativeMethods() as $method) {
            if ($method === $currentMethod || ! in_array($method, static::accessibleMethods(), true)) {
                continue;
            }

            $links[] = [
                'method' => $method,
                'label' => match ($method) {
                    'email_otp' => 'Sign in with email code instead',
                    'phone_otp' => 'Sign in with phone code instead',
                    default => 'Use alternative sign in',
                },
            ];
        }

        if ($currentMethod !== 'email_password' && static::defaultMethod() === 'email_password' && in_array('email_password', static::accessibleMethods(), true)) {
            $links[] = [
                'method' => 'email_password',
                'label' => 'Sign in with password instead',
            ];
        }

        return $links;
    }

    public static function fields(string $method): array
    {
        return (array) config("authentication.login.fields_per_method.{$method}", []);
    }

    public static function requiredFields(string $method): array
    {
        return array_keys(array_filter(
            static::fields($method),
            static fn (mixed $metadata): bool => (bool) data_get($metadata, 'required', false)
        ));
    }
}
