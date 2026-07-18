<?php

namespace Modules\Authentication\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Authentication\DTOs\LoginData;
use Modules\Authentication\DTOs\Events\UserLoggedInPayload;
use Modules\Authentication\Events\UserLoggedIn;
use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Services\FailedLoginService;
use Modules\Authentication\Services\TokenService;
use Modules\Authentication\Support\AccountStatusGate;
use Modules\Authentication\Support\LoginCredentialResolver;

class LoginUserAction
{
    public function __construct(
        protected TokenService $tokenService,
        protected FailedLoginService $failedLoginService,
    ) {}

    public function execute(LoginData $data, string $source = 'web'): array
    {
        $identifier = LoginCredentialResolver::identifier($data);
        $user = LoginCredentialResolver::resolveUser($data);
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

        event(new UserLoggedIn(UserLoggedInPayload::fromLogin($user, $data->authMethod, $source)));

        return [
            'success' => true,
            'user' => $user,
            'token' => $tokenData['token'] ?? null,
            'expires_at' => $tokenData['expires_at'] ?? null,
            'source' => $source,
        ];
    }
}
