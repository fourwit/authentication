<?php

namespace Modules\Authentication\Actions;

use Illuminate\Support\Facades\Auth;
use Modules\Authentication\DTOs\LoginData;
use Modules\Authentication\DTOs\Events\UserLoggedInPayload;
use Modules\Authentication\Events\UserLoggedIn;
use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Services\FailedLoginService;
use Modules\Authentication\Services\TokenService;
use Modules\Authentication\Services\VerificationCodeService;
use Modules\Authentication\Support\AccountStatusGate;
use Modules\Authentication\Support\LoginCredentialResolver;

class VerifyLoginOtpAction
{
    public function __construct(
        protected VerificationCodeService $verificationCodeService,
        protected FailedLoginService $failedLoginService,
        protected TokenService $tokenService,
    ) {}

    public function execute(LoginData $data, string $code, string $source = 'web'): array
    {
        $identifier = LoginCredentialResolver::identifier($data);
        $this->failedLoginService->ensureNotLocked($identifier);

        $channel = LoginCredentialResolver::channelFor($data->authMethod);
        $user = LoginCredentialResolver::resolveUser($data);

        if (! $user) {
            $this->failedLoginService->record($identifier);
            throw new InvalidCredentialsException();
        }

        AccountStatusGate::allowLogin($user);

        $verified = $this->verificationCodeService->verifyCode(
            $user->id,
            $channel,
            $code,
            $source,
            'login'
        );

        if (! $verified) {
            $this->failedLoginService->record($identifier);
            throw new InvalidCredentialsException();
        }

        $this->failedLoginService->clear($identifier);
        Auth::guard(config('authentication.guards.web', 'web'))->login($user, $data->remember);
        $tokenData = $this->tokenService->issueForLogin($user, $data->remember, $source);

        event(new UserLoggedIn(UserLoggedInPayload::fromLogin($user, $data->authMethod, $source)));

        return [
            'success' => true,
            'user' => $user,
            'token' => $tokenData['token'] ?? null,
            'expires_at' => $tokenData['expires_at'] ?? null,
            'source' => $source,
            'channel' => $channel,
        ];
    }
}
