<?php

namespace Modules\Authentication\Actions;

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Modules\Authentication\DTOs\EmailVerificationData;
use Modules\Authentication\DTOs\Events\EmailVerifiedPayload;
use Modules\Authentication\Events\EmailVerified;
use Modules\Authentication\Exceptions\InvalidVerificationTokenException;
use Modules\Authentication\Support\EmailVerificationCredentialResolver;
use Modules\Authentication\Support\VerificationConfig;

class VerifyEmailVerificationLinkAction
{
    public function execute(EmailVerificationData $data, string $source = 'web'): array
    {
        if (! VerificationConfig::enabled()) {
            return ['status' => 'disabled'];
        }

        $user = EmailVerificationCredentialResolver::resolveUser($data);

        if (! $user) {
            throw new InvalidVerificationTokenException();
        }

        if ($user->hasVerifiedEmail()) {
            event(new EmailVerified(EmailVerifiedPayload::fromUser($user, $source)));

            return ['status' => 'already_verified', 'user' => $user];
        }

        $user->forceFill(['email_verified_at' => Carbon::now()])->save();
        event(new Verified($user));
        event(new EmailVerified(EmailVerifiedPayload::fromUser($user, $source)));

        return ['status' => 'verified', 'user' => $user];
    }
}
