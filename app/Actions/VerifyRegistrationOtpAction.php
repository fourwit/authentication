<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\DTOs\Events\EmailVerifiedPayload;
use Modules\Authentication\Events\EmailVerified;
use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Authentication\Services\VerificationCodeService;

class VerifyRegistrationOtpAction
{
    public function __construct(
        protected RegistrationFollowUpService $registrationFollowUpService,
        protected VerificationCodeService $verificationCodeService,
        protected IssueRegistrationGrantAction $issueRegistrationGrantAction,
    ) {}

    public function execute(array $data, string $source = 'web'): array
    {
        $user = $this->registrationFollowUpService->resolveUserFromIdentifier($data);

        if (! $user) {
            throw new InvalidCredentialsException();
        }

        $authMethod = (string) ($data['auth_method'] ?? 'email_otp');
        $channel = $authMethod === 'phone_otp' ? 'phone' : 'email';
        $verified = $this->verificationCodeService->verifyCode(
            $user->id,
            $channel,
            (string) ($data['code'] ?? ''),
            $source,
            'register'
        );

        if (! $verified) {
            throw new InvalidCredentialsException();
        }

        $registrationGrant = $this->issueRegistrationGrantAction->execute(
            (int) $user->id,
            $authMethod,
            verified: true
        );

        $freshUser = $user->fresh();

        if ($channel === 'email') {
            event(new EmailVerified(EmailVerifiedPayload::fromUser($freshUser ?? $user, $source)));
        }

        return [
            'status' => 'verified',
            'user' => $freshUser,
            'next_step' => $this->registrationFollowUpService->nextStep($user),
            'registration_grant' => $registrationGrant,
        ];
    }
}
