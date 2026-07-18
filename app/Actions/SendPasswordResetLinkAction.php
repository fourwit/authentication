<?php

namespace Modules\Authentication\Actions;

use Illuminate\Support\Facades\Password;
use Modules\Authentication\DTOs\PasswordResetRequestData;
use Modules\Authentication\DTOs\Events\PasswordResetRequestedPayload;
use Modules\Authentication\Events\PasswordResetRequested;

class SendPasswordResetLinkAction
{
    public function execute(PasswordResetRequestData $data, string $source = 'web'): array
    {
        try {
            $broker = Password::broker();

            $status = $broker->sendResetLink(
                ['email' => $data->email],
                function ($user, $token) use ($data, $source) {
                    try {
                        $user->notifyNow(new \Modules\Authentication\Notifications\PasswordResetNotification($token));

                        if (app()->environment(['local', 'testing']) || config('app.debug')) {
                            try {
                                $resetUrl = url(route('authentication.password.reset', [
                                    'token' => $token,
                                ]));
                                \Illuminate\Support\Facades\Log::info('[PASSWORD RESET LINK] '.$resetUrl.' (for '.$data->email.')');
                            } catch (\Throwable $routeEx) {
                                \Illuminate\Support\Facades\Log::info('[PASSWORD RESET] Token generated for '.$data->email.' (check mailpit for the email)');
                            }
                        }
                    } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface | \Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Password reset email delivery failed (notifyNow)', [
                            'email' => $data->email,
                            'source' => $source,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            );

            event(new PasswordResetRequested(PasswordResetRequestedPayload::fromIdentifier($data->email ?? '', $source)));

            return ['status' => $status];
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface | \Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Password reset email delivery failed', [
                'email' => $data->email,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            event(new PasswordResetRequested(PasswordResetRequestedPayload::fromIdentifier($data->email ?? '', $source)));

            return ['status' => 'passwords.sent'];
        }
    }
}
