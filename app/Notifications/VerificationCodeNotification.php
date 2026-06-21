<?php

namespace Modules\Authentication\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Authenticatable $user,
        public string $channel,
        public string $destination,
        public string $plainCode
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = $this->user->name ?? $this->user->full_name ?? ($notifiable->name ?? '');
        return (new MailMessage)
            ->subject('Your Verification Code')
            ->greeting('Hello ' . $name)
            ->line('Your verification code is: ' . $this->plainCode)
            ->line('This code will expire in ' . config('authentication.otp.expires_minutes', 10) . ' minutes.')
            ->line('If you did not request this, please ignore this email.');
    }
}
