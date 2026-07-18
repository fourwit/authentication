<?php

namespace Modules\Authentication\Services;

use Modules\Authentication\Actions\ResetPasswordWithLinkAction;
use Modules\Authentication\Actions\ResetPasswordWithOtpAction;
use Modules\Authentication\Actions\ResolvePasswordResetEmailForTokenAction;
use Modules\Authentication\Actions\SendPasswordResetLinkAction;
use Modules\Authentication\Actions\SendPasswordResetOtpAction;
use Modules\Authentication\Actions\VerifyPasswordResetOtpAction;
use Modules\Authentication\DTOs\PasswordResetRequestData;
use Modules\Authentication\DTOs\ResetPasswordData;

class PasswordResetService
{
    public function __construct(
        protected SendPasswordResetLinkAction $sendPasswordResetLinkAction,
        protected SendPasswordResetOtpAction $sendPasswordResetOtpAction,
        protected VerifyPasswordResetOtpAction $verifyPasswordResetOtpAction,
        protected ResetPasswordWithLinkAction $resetPasswordWithLinkAction,
        protected ResetPasswordWithOtpAction $resetPasswordWithOtpAction,
        protected ResolvePasswordResetEmailForTokenAction $resolvePasswordResetEmailForTokenAction,
    ) {}

    public function sendResetLink(PasswordResetRequestData $data, string $source = 'web'): array
    {
        if ($data->authMethod !== 'link') {
            return $this->sendPasswordResetOtpAction->execute($data, $source);
        }

        return $this->sendPasswordResetLinkAction->execute($data, $source);
    }

    public function reset(ResetPasswordData $data, string $source = 'web'): array
    {
        if ($data->authMethod !== 'link') {
            return $this->resetPasswordWithOtpAction->execute($data, $source);
        }

        return $this->resetPasswordWithLinkAction->execute($data, $source);
    }

    public function verifyOtp(array $data, string $source = 'web'): array
    {
        return $this->verifyPasswordResetOtpAction->execute($data, $source);
    }

    public function getEmailForToken(string $token): ?string
    {
        return $this->resolvePasswordResetEmailForTokenAction->execute($token);
    }
}
