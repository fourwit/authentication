<?php

namespace Modules\Authentication\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Modules\Authentication\DTOs\PasswordResetRequestData;
use Modules\Authentication\DTOs\ResetPasswordData;
use Modules\Authentication\Exceptions\InvalidPasswordResetTokenException;
use Modules\Authentication\Repositories\PasswordResetRepository;
use Modules\Authentication\Support\AccountStatusGate;
use Modules\Authentication\Support\IdentityUserLookup;
use Modules\Identity\Facades\Identity;

class PasswordResetService
{
    public function __construct(
        protected VerificationCodeService $verificationCodeService,
        protected PasswordResetRepository $passwordResetRepository,
        protected TokenService $tokenService,
    ) {}

    public function sendResetLink(PasswordResetRequestData $data, string $source = 'web'): array
    {
        if ($data->authMethod !== 'link') {
            return $this->sendResetOtp($data, $source);
        }

        try {
            $broker = Password::broker();

            $status = $broker->sendResetLink(
                ['email' => $data->email],
                function ($user, $token) use ($data, $source) {
                    try {
                        // Force synchronous delivery (notifyNow) so the password reset email
                        // is sent immediately, exactly like we do for verification codes.
                        // This prevents the email from being stuck in the queue (database queue
                        // + no worker = not received).
                        //
                        // Use our custom notification so it uses the module's route name
                        // 'authentication.password.reset' instead of Laravel's default 'password.reset'.
                        $user->notifyNow(new \Modules\Authentication\Notifications\PasswordResetNotification($token));

                        // For easy localhost testing (mailpit or log), surface the link when in debug/local.
                        if (app()->environment(['local', 'testing']) || config('app.debug')) {
                            try {
                                $resetUrl = url(route('authentication.password.reset', [
                                    'token' => $token,
                                ]));
                                \Illuminate\Support\Facades\Log::info('[PASSWORD RESET LINK] ' . $resetUrl . ' (for ' . $data->email . ')');
                            } catch (\Throwable $routeEx) {
                                // Route may not be present in some host setups; log what we can.
                                \Illuminate\Support\Facades\Log::info('[PASSWORD RESET] Token generated for ' . $data->email . ' (check mailpit for the email)');
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

            return ['status' => $status];
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface | \Throwable $e) {
            // Log the failure so operators can detect mailer problems,
            // but we must never let this affect the response shape or message.
            \Illuminate\Support\Facades\Log::warning('Password reset email delivery failed', [
                'email' => $data->email,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            // Always return the same success-like status.
            // This, combined with the controller always using one message,
            // prevents leaking whether the email exists or whether delivery worked.
            return ['status' => 'passwords.sent'];
        }
    }

    public function reset(ResetPasswordData $data, string $source = 'web'): array
    {
        if ($data->authMethod !== 'link') {
            return $this->resetWithOtp($data, $source);
        }

        $status = Password::reset([
            'email' => $data->email,
            'password' => $data->password,
            'password_confirmation' => $data->passwordConfirmation ?? $data->password,
            'token' => $data->token,
        ], function ($user) use ($data) {
            $user->forceFill(['password' => Hash::make($data->password)])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw new InvalidPasswordResetTokenException();
        }

        return ['status' => $status, 'user' => Identity::findByEmail($data->email)];
    }

    public function verifyOtp(array $data, string $source = 'web'): array
    {
        $authMethod = (string) ($data['auth_method'] ?? 'email_otp');
        $identifier = $authMethod === 'phone_otp'
            ? ($data['phone'] ?? null)
            : ($data['email'] ?? null);
        $user = $authMethod === 'phone_otp'
            ? IdentityUserLookup::findByPhone($identifier)
            : Identity::findByEmail((string) $identifier);

        if (! $user || ! $identifier) {
            throw new InvalidPasswordResetTokenException();
        }

        $channel = $authMethod === 'phone_otp' ? 'phone' : 'email';
        $verified = $this->verificationCodeService->verifyCode(
            $user->id,
            $channel,
            (string) ($data['code'] ?? ''),
            $source,
            'forgot_password'
        );

        if (! $verified) {
            throw new InvalidPasswordResetTokenException();
        }

        $grant = $this->passwordResetRepository->createOtpGrant($user->id, $authMethod, (string) $identifier);

        return [
            'status' => 'verified',
            'user' => $user,
            'reset_grant' => $grant,
            'auth_method' => $authMethod,
            'email' => $authMethod === 'email_otp' ? (string) $identifier : null,
            'phone' => $authMethod === 'phone_otp' ? (string) $identifier : null,
            'next_step' => 'set_password',
        ];
    }

    /**
     * Resolve the email address associated with a password reset token.
     * Returns null if the token is invalid, expired, or does not exist.
     */
    public function getEmailForToken(string $token): ?string
    {
        $table = config('auth.passwords.users.table', 'password_reset_tokens');
        $expire = config('auth.passwords.users.expire', 60);

        $records = DB::table($table)->get(['email', 'token', 'created_at']);

        foreach ($records as $record) {
            if (Hash::check($token, $record->token)) {
                if (now()->diffInMinutes($record->created_at) <= $expire) {
                    return $record->email;
                }
                // Token expired - treat as invalid
                return null;
            }
        }

        return null;
    }

    protected function sendResetOtp(PasswordResetRequestData $data, string $source): array
    {
        $channel = $data->authMethod === 'phone_otp' ? 'phone' : 'email';
        $identifier = $channel === 'phone' ? $data->phone : $data->email;
        $user = $channel === 'phone'
            ? IdentityUserLookup::findByPhone($data->phone)
            : Identity::findByEmail((string) $data->email);

        if (! $user || ! $identifier) {
            return [
                'status' => 'passwords.sent',
                'auth_method' => $data->authMethod,
                'channel' => $channel,
                'destination' => $identifier,
            ];
        }

        AccountStatusGate::allowPasswordReset($user);

        $result = $this->verificationCodeService->sendCode(
            $user->id,
            $channel,
            $source,
            true,
            false,
            'forgot_password'
        );

        return $result + [
            'auth_method' => $data->authMethod,
            'channel' => $channel,
            'destination' => $result['destination'] ?? $identifier,
            'user' => $user,
        ];
    }

    protected function resetWithOtp(ResetPasswordData $data, string $source): array
    {
        $grant = $data->resetGrant ? $this->passwordResetRepository->consumeOtpGrant($data->resetGrant) : null;

        if (! $grant) {
            throw new InvalidPasswordResetTokenException();
        }

        $user = Identity::findById((int) $grant['user_id']);

        if (! $user || ! hash_equals((string) $grant['auth_method'], $data->authMethod)) {
            throw new InvalidPasswordResetTokenException();
        }

        $expectedIdentifier = (string) ($grant['identifier'] ?? '');
        $providedIdentifier = $data->authMethod === 'phone_otp'
            ? (string) ($data->phone ?? '')
            : (string) ($data->email ?? '');

        if ($expectedIdentifier === '' || $providedIdentifier === '' || ! hash_equals($expectedIdentifier, $providedIdentifier)) {
            throw new InvalidPasswordResetTokenException();
        }

        $user->forceFill(['password' => Hash::make($data->password)])->save();

        $response = [
            'status' => 'password_reset',
            'user' => $user,
        ];

        if ((bool) config('authentication.password_reset.auto_login_after_reset', false)) {
            $response['token'] = $this->tokenService->issue($user);
            $response['auto_login'] = true;
        }

        return $response;
    }
}
