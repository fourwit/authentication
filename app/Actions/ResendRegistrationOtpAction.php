<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Authentication\Services\VerificationCodeService;

class ResendRegistrationOtpAction
{
    public function __construct(
        protected RegistrationFollowUpService $registrationFollowUpService,
        protected VerificationCodeService $verificationCodeService,
    ) {}

    public function execute(array $data, string $source = 'web'): array
    {
        $user = $this->registrationFollowUpService->resolveUserFromIdentifier($data);

        if (! $user) {
            throw new InvalidCredentialsException();
        }

        $channel = ($data['auth_method'] ?? null) === 'phone_otp' ? 'phone' : 'email';

        return $this->verificationCodeService->resendCode($user->id, $channel, $source, 'register');
    }
}
