<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\DTOs\LoginData;
use Modules\Authentication\Events\FailedLoginRecorded;
use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Services\FailedLoginService;
use Modules\Authentication\Services\VerificationCodeService;
use Modules\Authentication\Support\AccountStatusGate;
use Modules\Authentication\Support\LoginCredentialResolver;

class InitiateLoginOtpAction
{
    public function __construct(
        protected VerificationCodeService $verificationCodeService,
        protected FailedLoginService $failedLoginService,
    ) {}

    public function execute(LoginData $data, string $source = 'web'): array
    {
        $channel = LoginCredentialResolver::channelFor($data->authMethod);
        $identifier = LoginCredentialResolver::identifier($data);
        $user = LoginCredentialResolver::resolveUser($data);

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

        event(new FailedLoginRecorded($identifier, $source));

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
}
