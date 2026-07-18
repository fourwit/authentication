<?php

namespace Modules\Authentication\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\DTOs\RegisterUserData;
use Modules\Authentication\DTOs\Events\UserRegisteredPayload;
use Modules\Authentication\Events\UserRegistered;
use Modules\Identity\Facades\Identity;

class RegisterUserAction
{
    public function execute(RegisterUserData $data, string $source = 'web'): array
    {
        $existingUser = $data->email ? Identity::findByEmail($data->email) : null;

        if ($existingUser) {
            if (! empty($existingUser->email_verified_at)) {
                throw ValidationException::withMessages([
                    'email' => 'An account with this email already exists. Please log in instead.',
                ]);
            }

            return [
                'user' => $existingUser,
                'was_created' => false,
                'reused_unverified' => true,
            ];
        }

        $resolvedName = $data->name
            ?? $data->firstName
            ?? $data->username
            ?? $data->email
            ?? $data->phone
            ?? 'User';

        [$resolvedFirstName, $resolvedLastName] = $this->resolveNameParts(
            $data->name,
            $data->firstName,
            $data->lastName
        );

        $payload = array_filter([
            'name' => $resolvedName,
            'email' => $data->email,
            'username' => $data->username,
            'phone' => $data->phone,
            'first_name' => $resolvedFirstName,
            'last_name' => $resolvedLastName,
            'email_verified_at' => null,
        ], static fn ($value) => ! is_null($value) && $value !== '');

        if ($data->authMethod === 'email_password') {
            $payload['password'] = Hash::make((string) $data->password);
        } else {
            $payload['password'] = Hash::make(str()->random(40));
        }

        $user = Identity::createUser($payload);

        event(new UserRegistered(UserRegisteredPayload::fromRegistration($user, $data->authMethod, $source)));

        return [
            'user' => $user,
            'was_created' => true,
            'reused_unverified' => false,
        ];
    }

    protected function resolveNameParts(?string $name, ?string $firstName, ?string $lastName): array
    {
        $firstName = is_string($firstName) ? trim($firstName) : null;
        $lastName = is_string($lastName) ? trim($lastName) : null;

        if (($firstName !== null && $firstName !== '') || ($lastName !== null && $lastName !== '')) {
            return [
                $firstName !== '' ? $firstName : null,
                $lastName !== '' ? $lastName : null,
            ];
        }

        $name = is_string($name) ? trim($name) : '';

        if ($name === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $parts = array_values(array_filter($parts, static fn (?string $part): bool => $part !== null && $part !== ''));

        if ($parts === []) {
            return [null, null];
        }

        $resolvedFirstName = $parts[0];
        $resolvedLastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

        return [$resolvedFirstName, $resolvedLastName];
    }
}
