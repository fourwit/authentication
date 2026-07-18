<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Support\VerificationConfig;

class SendLoginVerificationCodeAction
{
    public function __construct(
        protected ResendVerificationCode $resendVerificationCodeAction,
    ) {}

    public function execute(object $user, string $source = 'web'): void
    {
        if (! VerificationConfig::enabled() || VerificationConfig::method() !== 'code') {
            return;
        }

        $channel = VerificationConfig::ensureSupportedChannel();

        if ($channel !== 'email' || ! empty($user->email_verified_at)) {
            return;
        }

        try {
            $this->resendVerificationCodeAction->execute((int) $user->id, 'email', $source);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info(
                'Could not send verification code on login (cooldown/rate or error): '.$e->getMessage()
            );
        }
    }
}
