<?php

namespace Modules\Authentication\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Events\EmailVerified;
use Modules\Authentication\Notifications\WelcomeNotification;
use Modules\Identity\Facades\Identity;

class SendWelcomeEmail
{
    /**
     * Handle the event.
     */
    public function handle(EmailVerified $event): void
    {
        $user = Identity::findById($event->payload->userId);

        if (! $user) {
            return;
        }

        // Check if welcome has already been sent (prevents duplicates across
        // code verification, link verification, email/phone channels, etc.)
        if ($user->hasMetadata('welcome_sent_at')) {
            return;
        }

        try {
            // Send welcome email (sync for reliability, like verification codes)
            Notification::sendNow($user, new WelcomeNotification($user));

            // Mark as sent using metadata (no dedicated column needed)
            $user->setMetadata('welcome_sent_at', now());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to send welcome email', [
                'user_id' => $event->payload->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
