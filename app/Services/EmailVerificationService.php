<?php

namespace Modules\Authentication\Services;

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Modules\Authentication\DTOs\EmailVerificationData;
use Modules\Authentication\Exceptions\InvalidVerificationTokenException;
use Modules\Authentication\Support\IdentityUserLookup;
use Modules\Authentication\Support\VerificationConfig;
use Modules\Identity\Facades\Identity;

class EmailVerificationService
{
    public function send(EmailVerificationData $data, string $source = 'web'): array
    {
        if (! VerificationConfig::enabled()) {
            return ['status' => 'disabled'];
        }

        $user = $data->phone
            ? IdentityUserLookup::findByPhone($data->phone)
            : Identity::findByEmail((string) $data->email);
        if (! $user) {
            throw new InvalidVerificationTokenException();
        }

        if (VerificationConfig::method() === 'code') {
            $channel = VerificationConfig::ensureSupportedChannel();
            // delegate to code service
            app(\Modules\Authentication\Services\VerificationCodeService::class)->sendCode($user->id, $channel, $source);
            return ['status' => 'sent', 'user' => $user];
        }

        if (method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        return ['status' => 'sent', 'user' => $user];
    }

    public function verify(EmailVerificationData $data, string $source = 'web'): array
    {
        if (! VerificationConfig::enabled()) {
            return ['status' => 'disabled'];
        }

        $user = ($data->phone ? IdentityUserLookup::findByPhone($data->phone) : Identity::findByEmail((string) $data->email))
            ?? ($data->id ? Identity::findById((int) $data->id) : null);

        if (! $user) {
            throw new InvalidVerificationTokenException();
        }

        if ($user->hasVerifiedEmail()) {
            return ['status' => 'already_verified', 'user' => $user];
        }

        $user->forceFill(['email_verified_at' => Carbon::now()])->save();
        event(new Verified($user));

        return ['status' => 'verified', 'user' => $user];
    }
}
