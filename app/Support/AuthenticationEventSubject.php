<?php

namespace Modules\Authentication\Support;

class AuthenticationEventSubject
{
    public static function userId(mixed $user): ?int
    {
        if ($user === null) {
            return null;
        }

        if (isset($user->id)) {
            return (int) $user->id;
        }

        if (method_exists($user, 'getAuthIdentifier')) {
            $identifier = $user->getAuthIdentifier();

            return $identifier !== null ? (int) $identifier : null;
        }

        return null;
    }

    public static function email(mixed $user): ?string
    {
        if ($user === null || ! isset($user->email)) {
            return null;
        }

        $email = $user->email;

        return $email !== null && $email !== '' ? (string) $email : null;
    }

    public static function phone(mixed $user): ?string
    {
        if ($user === null) {
            return null;
        }

        foreach (['phone', 'phone_number'] as $field) {
            if (! empty($user->{$field})) {
                return (string) $user->{$field};
            }
        }

        return null;
    }

    public static function occurredAt(): string
    {
        return now()->toIso8601String();
    }
}
