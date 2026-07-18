<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Support\VerificationConfig;

class SendRegistrationVerificationCodeAction
{
    public function __construct(
        protected SendVerificationCode $sendVerificationCodeAction,
        protected ResendVerificationCode $resendVerificationCodeAction,
    ) {}

    public function execute(object $user, string $authMethod, bool $reusedUnverified, string $source = 'web'): void
    {
        if (! VerificationConfig::registrationRequiresVerification($authMethod)) {
            return;
        }

        if (VerificationConfig::method() !== 'code') {
            return;
        }

        $channel = VerificationConfig::ensureSupportedChannel();

        if ($channel !== 'email' || empty($user->email)) {
            return;
        }

        try {
            if ($reusedUnverified) {
                $this->resendVerificationCodeAction->execute((int) $user->id, $channel, $source);
            } else {
                $this->sendVerificationCodeAction->execute((int) $user->id, $channel, $source);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Could not send verification code on register: '.$e->getMessage());
        }
    }
}
