<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\DTOs\LoginData;
use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Services\FailedLoginService;
use Modules\Authentication\Services\VerificationCodeService;
use Modules\Authentication\Support\AccountStatusGate;
use Modules\Authentication\Support\LoginCredentialResolver;

class ResendLoginOtpAction
{
    public function __construct(
        protected FailedLoginService $failedLoginService,
        protected VerificationCodeService $verificationCodeService,
    ) {}

    public function execute(LoginData $data, string $source = 'web'): array
    {
        $this->failedLoginService->ensureNotLocked(LoginCredentialResolver::identifier($data));

        $user = LoginCredentialResolver::resolveUser($data);

        if (! $user) {
            throw new InvalidCredentialsException();
        }

        AccountStatusGate::allowLogin($user);

        $channel = LoginCredentialResolver::channelFor($data->authMethod);

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
}
