<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\DTOs\Events\EmailVerifiedPayload;
use Modules\Authentication\Events\EmailVerified;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Identity\Facades\Identity;

class VerifyEmailVerificationCodeAction
{
    public function __construct(
        protected VerifyCode $verifyCodeAction,
        protected RegistrationFollowUpService $registrationFollowUpService,
    ) {}

    public function execute(int $userId, string $channel, string $code, string $source = 'web'): array
    {
        $verified = $this->verifyCodeAction->execute($userId, $channel, $code, $source);

        if (! $verified) {
            return ['status' => 'failed'];
        }

        $user = Identity::findById($userId);

        if (! $user) {
            return ['status' => 'failed'];
        }

        event(new EmailVerified(EmailVerifiedPayload::fromUser($user, $source)));

        return [
            'status' => 'verified',
            'user' => $user,
            'next_step' => $user ? $this->registrationFollowUpService->nextStep($user) : 'dashboard',
        ];
    }
}
