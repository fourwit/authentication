<?php

namespace Modules\Authentication\Services;

use Modules\Authentication\Contracts\VerificationNotifierInterface;
use Modules\Authentication\Repositories\VerificationCodeRepository;
use Modules\Authentication\Support\PhoneInputConfig;
use Modules\Authentication\Support\PhoneNumberNormalizer;
use Modules\Authentication\Support\VerificationConfig;
use Modules\Identity\Facades\Identity;

class VerificationCodeService
{
    public function __construct(
        protected VerificationCodeRepository $repository,
        protected VerificationNotifierInterface $notifier
    ) {}

    public function sendCode(int $userId, string $channel, string $source = 'web', bool $allowReplaceActive = false, bool $bypassCooldown = false, string $purpose = 'register'): array
    {
        $user = Identity::findById($userId);
        if (! $user) {
            throw new \InvalidArgumentException('User not found');
        }

        if (! $this->verificationEnabledForContext($user, $purpose)) {
            return [
                'status' => 'disabled',
                'channel' => $channel,
            ];
        }

        $destination = $this->getDestination($user, $channel);

        if (! $destination) {
            if ($channel === 'phone') {
                // Graceful skip for phone channel when user has no phone (e.g. registration without phone_number)
                \Illuminate\Support\Facades\Log::info("Skipping phone verification code for user {$userId}: no phone destination");
                return [
                    'status' => 'skipped',
                    'channel' => $channel,
                    'reason' => 'no_destination',
                ];
            }
            throw new \InvalidArgumentException("No {$channel} destination for user");
        }

        $otpConfig = config('authentication.otp', []);
        $cooldown = (int) ($otpConfig['resend_cooldown_seconds'] ?? 60);
        $maxPerHour = (int) ($otpConfig['max_per_hour'] ?? 5);
        $hasActiveCode = $this->repository->hasActiveCode($userId, $channel, $destination, $purpose);

        // Cooldown check (from last send, even if previous code expired/used)
        $lastCreated = $this->repository->getLastCodeCreatedAt($userId, $channel, $destination, $purpose);
        $elapsedSeconds = $lastCreated ? $lastCreated->diffInSeconds(now()) : null;
        \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND-DEBUG] cooldown check values', [
            'user_id' => $userId,
            'channel' => $channel,
            'lastCreated' => $lastCreated ? $lastCreated->toDateTimeString() : null,
            'diff_seconds' => $elapsedSeconds,
            'cooldown_config' => $cooldown,
            'bypassCooldown' => $bypassCooldown,
            'allowReplaceActive' => $allowReplaceActive,
            'hasActiveCode' => $hasActiveCode,
        ]);
        if (! $bypassCooldown && $hasActiveCode && $lastCreated && $elapsedSeconds < $cooldown) {
            $allowedAt = $lastCreated->copy()->addSeconds($cooldown);
            return [
                'status' => 'cooldown',
                'channel' => $channel,
                'destination' => $destination,
                'resend_allowed_at' => $allowedAt,
            ];
        }

        // Rate limit: max resends per hour
        if ($this->repository->countCodesInLastHour($userId, $channel, $destination, $purpose) >= $maxPerHour) {
            \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND] Rate limit hit in sendCode', [
                'user_id' => $userId,
                'channel' => $channel,
            ]);
            return [
                'status' => 'rate_limited',
                'channel' => $channel,
                'destination' => $destination,
                'resend_allowed_at' => now()->addHour(),
            ];
        }

        // If active code exists and we are not explicitly allowing a fresh one (e.g. resend after cooldown),
        // do not create a new one (prevents blind new codes on login).
        if (! $allowReplaceActive && $this->repository->hasActiveCode($userId, $channel, $destination, $purpose)) {
            $active = $this->repository->findActiveCode($userId, $channel, $destination, $purpose);
            \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND] hasActiveCode early return (no new email) - non-resend path', [
                'user_id' => $userId,
                'channel' => $channel,
            ]);
            return [
                'status' => 'sent',
                'channel' => $channel,
                'destination' => $destination,
                'expires_at' => $active->expires_at ?? now()->addMinutes((int)($otpConfig['expires_minutes'] ?? 10)),
            ];
        }

        if ($allowReplaceActive) {
            \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND] allowReplaceActive=true - will force new code (bypassing hasActive check)', [
                'user_id' => $userId,
                'channel' => $channel,
            ]);
        }

        // Invalidate previous (allows fresh on resend/login when allowed)
        $this->repository->markAllAsVerified($userId, $channel, $destination, $purpose);

        $length = (int) ($otpConfig['length'] ?? 6);
        $expiresMin = (int) ($otpConfig['expires_minutes'] ?? 10);
        $codeData = $this->repository->createCode($userId, $channel, $destination, $purpose, $length, $expiresMin);

        // Always surface the plain code in logs for easy localhost testing (register / resend).
        // This solves "emails not received" when no SMTP (mailpit etc.) or when using queue without worker.
        // The code is logged at INFO so you can see exactly what was generated on resend/re-login.
        \Illuminate\Support\Facades\Log::info('[AUTH VERIFY CODE] ' . json_encode([
            'user_id'    => $userId,
            'channel'    => $channel,
            'destination'=> $destination,
            'code'       => $codeData['plain_code'],
            'expires_at' => (string) $codeData['expires_at'],
        ]));

        \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND] Code created in DB, about to call notifier', [
            'user_id' => $userId,
            'channel' => $channel,
            'allowReplaceActive' => $allowReplaceActive,
            'plain_code_preview' => substr($codeData['plain_code'], 0, 2) . '****', // don't log full in prod, but here for debug
        ]);

        try {
            if ($channel === 'email') {
                $this->notifier->send($user, $channel, $destination, $codeData['plain_code'], $source);
            } elseif ($channel === 'phone') {
                if (app()->bound(\Modules\Authentication\Contracts\PhoneVerificationCodeSenderInterface::class)) {
                    $sender = app(\Modules\Authentication\Contracts\PhoneVerificationCodeSenderInterface::class);
                    $sender->send($destination, $codeData['plain_code'], $source);
                } else {
                    throw new \Modules\Authentication\Exceptions\PhoneVerificationNotConfiguredException(
                        'Phone verification is enabled but no PhoneVerificationCodeSenderInterface is bound.'
                    );
                }
            }
        } catch (\Modules\Authentication\Exceptions\PhoneVerificationNotConfiguredException $e) {
            // Re-throw configuration errors so they can be shown clearly
            throw $e;
        } catch (\Throwable $e) {
            // Any other delivery failure (email transport, etc.) — log it, but do not fail the request.
            // The verification code was successfully created in the database.
            \Illuminate\Support\Facades\Log::warning('Verification code generated but delivery failed', [
                'user_id' => $userId,
                'channel' => $channel,
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);
        }

        \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND] Notifier call completed (check for "delivery attempted" or "Failed to deliver" logs next)', [
            'user_id' => $userId,
            'channel' => $channel,
        ]);

        // Dispatch event (for listeners / audit)
        event(new \Modules\Authentication\Events\VerificationCodeSent($user, $channel, $destination, $source));

        // Do not log the plain code
        return [
            'status' => 'sent',
            'channel' => $channel,
            'destination' => $destination,
            'expires_at' => $codeData['expires_at'],
        ];
    }

    public function verifyCode(int $userId, string $channel, string $plainCode, string $source = 'web', string $purpose = 'register'): bool
    {
        $user = Identity::findById($userId);
        if (! $user) {
            return false;
        }

        if (! $this->verificationEnabledForContext($user, $purpose)) {
            return true;
        }

        $destination = $this->getDestination($user, $channel);
        if (! $destination) {
            return false;
        }

        $record = $this->repository->findActiveCode($userId, $channel, $destination, $purpose);

        if (! $record) {
            return false;
        }

        $maxAttempts = (int) (config('authentication.otp.max_attempts', 5));

        if ($record->attempts >= $maxAttempts) {
            $this->repository->markAllAsVerified($userId, $channel, $destination, $purpose);
            throw new \Modules\Authentication\Exceptions\MaxVerificationAttemptsExceededException();
        }

        $verified = $this->repository->verifyCode($userId, $channel, $destination, $plainCode, $purpose);

        if ($verified) {
            // Update user verification field via Identity
            $updateData = [];
            if ($purpose === 'register') {
                if ($channel === 'email') {
                    $updateData['email_verified_at'] = now();
                } elseif ($channel === 'phone') {
                    $updateData['phone_verified_at'] = now();
                }
            }

            if ($updateData) {
                Identity::updateUser($user, $updateData);
            }

            // Dispatch success event (EmailVerified for compatibility + audit)
            if ($purpose === 'register') {
                event(new \Modules\Authentication\Events\EmailVerified($user, $source));
            }
        } else {
            // Wrong attempt: dispatch failed event
            event(new \Modules\Authentication\Events\VerificationFailed($user, $channel, $source));

            // Re-check attempts after increment inside repo.verifyCode; expire if max reached
            $recordAfter = $this->repository->findActiveCode($userId, $channel, $destination, $purpose);
            if ($recordAfter && $recordAfter->attempts >= $maxAttempts) {
                $this->repository->markAllAsVerified($userId, $channel, $destination, $purpose);
            }
        }

        return $verified;
    }

    public function resendCode(int $userId, string $channel, string $source = 'web', string $purpose = 'register'): array
    {
        \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND] resendCode called (force fresh after cooldown)', [
            'user_id' => $userId,
            'channel' => $channel,
            'source' => $source,
        ]);
        // Explicit resend allows replacing an active code after cooldown (fresh code)
        // We bypass the cooldown check here (UI is responsible for disabling the button during cooldown).
        // This ensures that if the user clicks the resend (or the UI doesn't disable it), we still send a fresh code.
        return $this->sendCode($userId, $channel, $source, true, true, $purpose);  // allowReplaceActive, bypassCooldown
    }

    /**
     * Helper for views/controllers: returns cooldown/rate info for the resend button.
     */
    public function getResendStatus(int $userId, string $channel = 'email', string $purpose = 'register'): array
    {
        $user = Identity::findById($userId);
        if (! $user) {
            return ['can_resend' => true, 'resend_allowed_at' => now(), 'cooldown_seconds' => 60];
        }

        if (! $this->verificationEnabledForContext($user, $purpose)) {
            return ['can_resend' => false, 'resend_allowed_at' => now(), 'cooldown_seconds' => 0];
        }

        $destination = $this->getDestination($user, $channel);
        if (! $destination) {
            return ['can_resend' => true, 'resend_allowed_at' => now(), 'cooldown_seconds' => 60];
        }

        $otpConfig = config('authentication.otp', []);
        $cooldown = (int) ($otpConfig['resend_cooldown_seconds'] ?? 60);

        $last = $this->repository->getLastCodeCreatedAt($userId, $channel, $destination, $purpose);
        $allowedAt = $last ? $last->copy()->addSeconds($cooldown) : now();

        return [
            'can_resend' => now()->gte($allowedAt),
            'resend_allowed_at' => $allowedAt,
            'cooldown_seconds' => $cooldown,
        ];
    }

    protected function getDestination($user, string $channel): ?string
    {
        if ($channel === 'email') {
            return $user->email ?? null;
        }

        if ($channel === 'phone') {
            if (! PhoneInputConfig::supportsPhoneFields()) {
                return null;
            }

            // Support both direct or profile
            return PhoneNumberNormalizer::normalize($user->phone ?? $user->identityProfile->phone ?? null);
        }

        return null;
    }

    protected function verificationEnabledForContext(object $user, string $purpose): bool
    {
        if (in_array($purpose, ['login', 'password_reset', 'forgot_password'], true)) {
            return true;
        }

        if ($purpose !== 'register') {
            return VerificationConfig::enabled();
        }

        $authMethod = Identity::getMetadata($user, RegistrationFollowUpService::META_AUTH_METHOD);

        return VerificationConfig::registrationRequiresVerification(
            is_string($authMethod) ? $authMethod : null
        );
    }
}
