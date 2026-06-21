<?php

namespace Modules\Authentication\Notifiers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Contracts\VerificationNotifierInterface;
use Modules\Authentication\Notifications\VerificationCodeNotification;

class EmailVerificationCodeNotifier implements VerificationNotifierInterface
{
    public function send(Authenticatable $user, string $channel, string $destination, string $plainCode, string $source = 'web'): void
    {
        if ($channel !== 'email') {
            \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND-NOTIFIER] Skipping non-email channel', ['channel' => $channel]);
            return;
        }

        \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND-NOTIFIER] Email notifier reached - calling sendNow', [
            'user_id' => $user->getAuthIdentifier(),
            'destination' => $destination,
            'source' => $source,
        ]);

        try {
            // sendNow on the real (Identity) user model. The model uses the Notifiable trait.
            // We validated $destination already. sendNow forces immediate delivery without
            // depending on a queue worker (addresses previous "not received on create" reports).
            // Delivery errors are caught so a transient mail issue never breaks the auth flow.
            Notification::sendNow($user, new VerificationCodeNotification($user, $channel, $destination, $plainCode));

            \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND-NOTIFIER] sendNow completed without exception - email should be in mailpit if transport ok', [
                'destination' => $destination,
                'user_id' => $user->getAuthIdentifier(),
            ]);
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface | \Throwable $e) {
            // Email server unavailable, misconfigured, or temporary failure.
            // We still created a valid code in the database, so user can resend or use it if they have it.
            \Illuminate\Support\Facades\Log::warning('[VERIFY-RESEND-NOTIFIER] Failed to deliver verification code email', [
                'user_id' => $user->getAuthIdentifier(),
                'destination' => $destination,
                'source' => $source,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            // Do not re-throw. The code/token was generated successfully.
        }
    }
}
