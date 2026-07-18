<?php

namespace Modules\Authentication\Services;

use Modules\Authentication\Actions\SendEmailVerificationCodeAction;
use Modules\Authentication\Actions\SendEmailVerificationLinkAction;
use Modules\Authentication\Actions\VerifyEmailVerificationLinkAction;
use Modules\Authentication\DTOs\EmailVerificationData;
use Modules\Authentication\Support\VerificationConfig;

class EmailVerificationService
{
    public function __construct(
        protected SendEmailVerificationLinkAction $sendEmailVerificationLinkAction,
        protected SendEmailVerificationCodeAction $sendEmailVerificationCodeAction,
        protected VerifyEmailVerificationLinkAction $verifyEmailVerificationLinkAction,
    ) {}

    public function send(EmailVerificationData $data, string $source = 'web'): array
    {
        if (VerificationConfig::method() === 'code') {
            return $this->sendEmailVerificationCodeAction->execute($data, $source);
        }

        return $this->sendEmailVerificationLinkAction->execute($data, $source);
    }

    public function verify(EmailVerificationData $data, string $source = 'web'): array
    {
        return $this->verifyEmailVerificationLinkAction->execute($data, $source);
    }
}
