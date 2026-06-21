<?php

namespace Modules\Authentication\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Authentication\DTOs\LoginData;
use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Exceptions\UnsupportedLoginMethodException;
use Modules\Authentication\Support\AccountStatusGate;
use Modules\Authentication\Support\IdentityUserLookup;
use Modules\Identity\Facades\Identity;

class AuthenticationService
{
    public function __construct(
        protected TokenService $tokenService,
        protected FailedLoginService $failedLoginService,
    ) {}

    public function login(LoginData $data, string $source = 'web'): array
    {
        if ($data->authMethod !== 'email_password') {
            throw new UnsupportedLoginMethodException('OTP login is not implemented yet.');
        }

        $identifier = $data->email ?? $data->phone ?? '';
        $user = $data->phone
            ? IdentityUserLookup::findByPhone($data->phone)
            : Identity::findByEmail((string) $data->email);

        $authenticated = false;

        if ($user) {
            AccountStatusGate::allowLogin($user);
        }

        if ($user && $data->email) {
            $authenticated = Auth::guard(config('authentication.guards.web', 'web'))->attempt([
                'email' => $data->email,
                'password' => (string) $data->password,
            ], $data->remember);
        } elseif ($user && $data->phone) {
            $authenticated = Hash::check((string) $data->password, (string) $user->password);

            if ($authenticated) {
                Auth::guard(config('authentication.guards.web', 'web'))->login($user, $data->remember);
            }
        }

        if (! $user || ! $authenticated) {
            $this->failedLoginService->record($identifier);
            throw new InvalidCredentialsException();
        }

        $this->failedLoginService->clear($identifier);
        $tokenData = $this->tokenService->issueForLogin($user, $data->remember, $source);

        return [
            'success' => true,
            'user' => $user,
            'token' => $tokenData['token'] ?? null,
            'expires_at' => $tokenData['expires_at'] ?? null,
            'source' => $source,
        ];
    }
}
