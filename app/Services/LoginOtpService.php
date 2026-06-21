<?php

namespace Modules\Authentication\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Authentication\DTOs\LoginData;
use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Support\AccountStatusGate;
use Modules\Authentication\Support\IdentityUserLookup;
use Modules\Identity\Facades\Identity;

class LoginOtpService
{
    public function __construct(
        protected VerificationCodeService $verificationCodeService,
        protected FailedLoginService $failedLoginService,
        protected TokenService $tokenService,
    ) {}

    public function initiate(LoginData $data, string $source = 'web'): array
    {
        $channel = $this->channelFor($data->authMethod);
        $identifier = $data->email ?? $data->phone ?? '';
        $user = $this->resolveUser($data);

        if (! $user) {
            $this->failedLoginService->record($identifier);
            throw new InvalidCredentialsException();
        }

        AccountStatusGate::allowLogin($user);

        $result = $this->verificationCodeService->sendCode(
            $user->id,
            $channel,
            $source,
            true,
            false,
            'login'
        );

        return [
            'status' => $result['status'] ?? 'sent',
            'user' => $user,
            'channel' => $channel,
            'destination' => $result['destination'] ?? ($data->email ?? $data->phone),
            'expires_at' => $result['expires_at'] ?? null,
            'resend_allowed_at' => $result['resend_allowed_at'] ?? null,
            'source' => $source,
        ];
    }

    public function verify(LoginData $data, string $code, string $source = 'web'): array
    {
        $channel = $this->channelFor($data->authMethod);
        $identifier = $data->email ?? $data->phone ?? '';
        $user = $this->resolveUser($data);

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

        return [
            'success' => true,
            'user' => $user,
            'token' => $tokenData['token'] ?? null,
            'expires_at' => $tokenData['expires_at'] ?? null,
            'source' => $source,
            'channel' => $channel,
        ];
    }

    public function resend(LoginData $data, string $source = 'web'): array
    {
        $user = $this->resolveUser($data);

        if (! $user) {
            throw new InvalidCredentialsException();
        }

        AccountStatusGate::allowLogin($user);

        $channel = $this->channelFor($data->authMethod);

        return $this->verificationCodeService->resendCode(
            $user->id,
            $channel,
            $source,
            'login'
        ) + [
            'user' => $user,
            'channel' => $channel,
        ];
    }

    protected function resolveUser(LoginData $data): mixed
    {
        return $data->phone
            ? IdentityUserLookup::findByPhone($data->phone)
            : Identity::findByEmail((string) $data->email);
    }

    protected function channelFor(string $authMethod): string
    {
        return $authMethod === 'phone_otp' ? 'phone' : 'email';
    }
}
