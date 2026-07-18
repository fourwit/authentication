<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\DTOs\EmailVerificationData;
use Modules\Authentication\DTOs\Events\EmailVerificationSentPayload;
use Modules\Authentication\Events\EmailVerificationSent;
use Modules\Authentication\Exceptions\InvalidVerificationTokenException;
use Modules\Authentication\Support\EmailVerificationCredentialResolver;
use Modules\Authentication\Support\VerificationConfig;

class SendEmailVerificationLinkAction
{
    public function execute(EmailVerificationData $data, string $source = 'web'): array
    {
        if (! VerificationConfig::enabled()) {
            event(new EmailVerificationSent(EmailVerificationSentPayload::fromIdentifier(EmailVerificationCredentialResolver::identifier($data), $source)));

            return ['status' => 'disabled'];
        }

        $user = EmailVerificationCredentialResolver::resolveUser($data);

        if (! $user) {
            throw new InvalidVerificationTokenException();
        }

        if (method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        event(new EmailVerificationSent(EmailVerificationSentPayload::fromIdentifier(EmailVerificationCredentialResolver::identifier($data), $source)));

        return ['status' => 'sent', 'user' => $user];
    }
}
