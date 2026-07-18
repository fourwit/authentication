<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\DTOs\EmailVerificationData;
use Modules\Authentication\Events\EmailVerificationSent;
use Modules\Authentication\Exceptions\InvalidVerificationTokenException;
use Modules\Authentication\Services\VerificationCodeService;
use Modules\Authentication\Support\EmailVerificationCredentialResolver;
use Modules\Authentication\Support\VerificationConfig;

class SendEmailVerificationCodeAction
{
    public function __construct(
        protected VerificationCodeService $verificationCodeService,
    ) {}

    public function execute(EmailVerificationData $data, string $source = 'web'): array
    {
        if (! VerificationConfig::enabled()) {
            event(new EmailVerificationSent(EmailVerificationCredentialResolver::identifier($data), $source));

            return ['status' => 'disabled'];
        }

        $user = EmailVerificationCredentialResolver::resolveUser($data);

        if (! $user) {
            throw new InvalidVerificationTokenException();
        }

        $channel = VerificationConfig::ensureSupportedChannel();
        $this->verificationCodeService->sendCode($user->id, $channel, $source);

        event(new EmailVerificationSent(EmailVerificationCredentialResolver::identifier($data), $source));

        return ['status' => 'sent', 'user' => $user];
    }
}
