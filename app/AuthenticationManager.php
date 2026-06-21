<?php

namespace Modules\Authentication;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Modules\Authentication\DTOs\EmailVerificationData;
use Modules\Authentication\DTOs\LoginData;
use Modules\Authentication\DTOs\PasswordResetRequestData;
use Modules\Authentication\DTOs\RegisterUserData;
use Modules\Authentication\DTOs\ResetPasswordData;
use Modules\Authentication\Events\EmailVerified;
use Modules\Authentication\Events\EmailVerificationSent;
use Modules\Authentication\Events\FailedLoginRecorded;
use Modules\Authentication\Events\PasswordResetCompleted;
use Modules\Authentication\Events\PasswordResetRequested;
use Modules\Authentication\Events\UserLoggedIn;
use Modules\Authentication\Events\UserLoggedOut;
use Modules\Authentication\Events\UserRegistered;
use Modules\Authentication\Repositories\AuthTokenRepository;
use Modules\Authentication\Repositories\FailedLoginRepository;
use Modules\Authentication\Repositories\PasswordResetRepository;
use Modules\Authentication\Actions\ResendVerificationCode;
use Modules\Authentication\Actions\SendVerificationCode;
use Modules\Authentication\Actions\VerifyCode;
use Modules\Authentication\Services\AuthenticationService;
use Modules\Authentication\Services\EmailVerificationService;
use Modules\Authentication\Services\FailedLoginService;
use Modules\Authentication\Services\LoginOtpService;
use Modules\Authentication\Services\PasswordResetService;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Authentication\Services\RegistrationService;
use Modules\Authentication\Services\TokenService;
use Modules\Authentication\Services\VerificationCodeService;
use Modules\Authentication\Support\PhoneInputConfig;
use Modules\Authentication\Support\VerificationConfig;
use Modules\Identity\Facades\Identity;

class AuthenticationManager
{
    public function __construct(
        protected AuthenticationService $authenticationService,
        protected RegistrationService $registrationService,
        protected PasswordResetService $passwordResetService,
        protected EmailVerificationService $emailVerificationService,
        protected TokenService $tokenService,
        protected FailedLoginService $failedLoginService,
        protected LoginOtpService $loginOtpService,
        protected RegistrationFollowUpService $registrationFollowUpService,
        protected AuthTokenRepository $authTokenRepository,
        protected PasswordResetRepository $passwordResetRepository,
        protected FailedLoginRepository $failedLoginRepository,
        protected VerificationCodeService $verificationCodeService,
        protected SendVerificationCode $sendVerificationCodeAction,
        protected VerifyCode $verifyCodeAction,
        protected ResendVerificationCode $resendVerificationCodeAction,
    ) {}

    public function login(array $data, string $source = 'web'): array
    {
        $dto = LoginData::fromArray($data);
        $identifier = $dto->email ?? $dto->phone ?? '';
        $this->failedLoginService->ensureNotLocked($identifier);
        $result = $dto->authMethod === 'email_password'
            ? $this->authenticationService->login($dto, $source)
            : $this->loginOtpService->initiate($dto, $source);

        if ($result['success'] ?? false) {
            event(new UserLoggedIn($result['user'], $source));

            // Send fresh verification code on login if unverified (per user request), but do not do so blindly
            // (cooldown/rate limits are enforced inside sendCode).
            if (VerificationConfig::enabled() && VerificationConfig::method() === 'code') {
                $user = $result['user'];
                $channel = VerificationConfig::ensureSupportedChannel();
                if ($channel === 'email' && empty($user->email_verified_at)) {
                    try {
                        $this->resendVerificationCodeAction->execute($user->id, 'email', $source);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::info('Could not send verification code on login (cooldown/rate or error): ' . $e->getMessage());
                    }
                }
            }
        } else {
            event(new FailedLoginRecorded($identifier, $source));
        }

        return $result;
    }

    public function verifyLoginOtp(array $data, string $source = 'web'): array
    {
        $dto = LoginData::fromArray($data);
        $identifier = $dto->email ?? $dto->phone ?? '';
        $this->failedLoginService->ensureNotLocked($identifier);

        return $this->loginOtpService->verify($dto, (string) ($data['code'] ?? ''), $source);
    }

    public function resendLoginOtp(array $data, string $source = 'web'): array
    {
        $dto = LoginData::fromArray($data);
        $identifier = $dto->email ?? $dto->phone ?? '';
        $this->failedLoginService->ensureNotLocked($identifier);

        return $this->loginOtpService->resend($dto, $source);
    }

    public function logout($user = null, string $source = 'web'): void
    {
        $this->tokenService->revokeCurrentToken($user);
        event(new UserLoggedOut($user, $source));
    }

    public function register(array $data, string $source = 'web'): array
    {
        $dto = RegisterUserData::fromArray($data);
        $registration = $this->registrationService->register($dto, $source);
        $user = $registration['user'];
        $wasCreated = (bool) ($registration['was_created'] ?? false);
        $reusedUnverified = (bool) ($registration['reused_unverified'] ?? false);

        if ($wasCreated) {
            event(new UserRegistered($user, $source));
        }

        if ($this->registrationFollowUpService->isOtpRegistrationMethod($dto->authMethod)) {
            $this->registrationFollowUpService->initializeForOtpRegistration($user, $dto->authMethod);
        }

        // Send verification code(s) on registration per (new) config
        if (VerificationConfig::registrationRequiresVerification($dto->authMethod) && VerificationConfig::method() === 'code') {
            $channel = VerificationConfig::ensureSupportedChannel();
            if ($channel === 'email') {
                $hasEmail = !empty($user->email);
                if ($hasEmail) {
                    try {
                        if ($reusedUnverified) {
                            $this->resendVerificationCode(['user_id' => $user->id, 'channel' => 'email'], $source);
                        } else {
                            // Register send allows fresh (first time, no active code)
                            $this->sendVerificationCode(['user_id' => $user->id, 'channel' => 'email'], $source);
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Could not send verification code on register: ' . $e->getMessage());
                    }
                }
            }
        }

        return [
            'user' => $user,
            'was_created' => $wasCreated,
            'reused_unverified' => $reusedUnverified,
        ];
    }

    public function user($request = null): ?Authenticatable
    {
        $request = $request instanceof Request ? $request : request();
        return $request->user();
    }

    public function sendPasswordReset(array $data, string $source = 'web'): array
    {
        $dto = PasswordResetRequestData::fromArray($data);
        $response = $this->passwordResetService->sendResetLink($dto, $source);
        event(new PasswordResetRequested($dto->email ?? $dto->phone ?? '', $source));
        return $response;
    }

    public function resetPassword(array $data, string $source = 'web'): array
    {
        $dto = ResetPasswordData::fromArray($data);
        $response = $this->passwordResetService->reset($dto, $source);

        if (($response['user'] ?? null) && (bool) config('authentication.password_reset.auto_login_after_reset', false)) {
            $response['auto_login'] = true;
            $response['token'] = $response['token'] ?? $this->tokenService->issue($response['user']);
        }

        event(new PasswordResetCompleted($response['user'] ?? null, $source));
        return $response;
    }

    public function verifyPasswordResetOtp(array $data, string $source = 'web'): array
    {
        return $this->passwordResetService->verifyOtp($data, $source);
    }

    /**
     * Resolve the email for a given password reset token (for token-only reset URLs).
     */
    public function getEmailForToken(string $token): ?string
    {
        return $this->passwordResetService->getEmailForToken($token);
    }

    public function sendEmailVerification(array $data, string $source = 'web'): array
    {
        $dto = EmailVerificationData::fromArray($data);
        $response = $this->emailVerificationService->send($dto, $source);
        event(new EmailVerificationSent($dto->email ?? $dto->phone ?? '', $source));
        return $response;
    }

    public function verifyEmail(array $data, string $source = 'web'): array
    {
        $dto = EmailVerificationData::fromArray($data);
        $response = $this->emailVerificationService->verify($dto, $source);
        event(new EmailVerified($response['user'] ?? null, $source));
        return $response;
    }

    public function sendVerificationCode(array $data, string $source = 'web'): array
    {
        $userId = $data['user_id'] ?? auth()->id();
        $channel = $data['channel'] ?? VerificationConfig::ensureSupportedChannel();

        return $this->sendVerificationCodeAction->execute($userId, $channel, $source);
    }

    public function verifyCode(array $data, string $source = 'web'): array
    {
        $userId = $data['user_id'] ?? auth()->id();
        $channel = $data['channel'] ?? VerificationConfig::ensureSupportedChannel();
        $code = $data['code'] ?? '';

        $verified = $this->verifyCodeAction->execute($userId, $channel, $code, $source);

        if ($verified) {
            $user = Identity::findById($userId);

            // The EmailVerified event is dispatched inside VerificationCodeService.
            // The SendWelcomeEmail listener will handle sending the welcome email
            // (checks welcome_sent_at via metadata, sends once, updates the flag via metadata).
            $nextStep = $user ? $this->registrationFollowUpService->nextStep($user) : 'dashboard';

            return ['status' => 'verified', 'user' => $user, 'next_step' => $nextStep];
        }

        return ['status' => 'failed'];
    }

    public function resendVerificationCode(array $data, string $source = 'web'): array
    {
        $userId = $data['user_id'] ?? auth()->id();
        $channel = $data['channel'] ?? VerificationConfig::ensureSupportedChannel();

        return $this->resendVerificationCodeAction->execute($userId, $channel, $source);
    }

    public function verifyRegistrationOtp(array $data, string $source = 'web'): array
    {
        $user = $this->registrationFollowUpService->resolveUserFromIdentifier($data);

        if (! $user) {
            throw new \Modules\Authentication\Exceptions\InvalidCredentialsException();
        }

        $channel = ($data['auth_method'] ?? null) === 'phone_otp' ? 'phone' : 'email';
        $verified = $this->verificationCodeService->verifyCode(
            $user->id,
            $channel,
            (string) ($data['code'] ?? ''),
            $source,
            'register'
        );

        if (! $verified) {
            throw new \Modules\Authentication\Exceptions\InvalidCredentialsException();
        }

        return [
            'status' => 'verified',
            'user' => $user->fresh(),
            'next_step' => $this->registrationFollowUpService->nextStep($user),
        ];
    }

    public function resendRegistrationOtp(array $data, string $source = 'web'): array
    {
        $user = $this->registrationFollowUpService->resolveUserFromIdentifier($data);

        if (! $user) {
            throw new \Modules\Authentication\Exceptions\InvalidCredentialsException();
        }

        $channel = ($data['auth_method'] ?? null) === 'phone_otp' ? 'phone' : 'email';

        return $this->verificationCodeService->resendCode($user->id, $channel, $source, 'register');
    }

    public function setRegistrationPassword(array $data, string $source = 'web'): array
    {
        $user = isset($data['user_id']) ? Identity::findById((int) $data['user_id']) : auth()->user();

        if (! $user) {
            throw new \Modules\Authentication\Exceptions\InvalidCredentialsException();
        }

        $this->registrationFollowUpService->setPassword($user, (string) $data['password']);

        return [
            'status' => 'password_set',
            'user' => $user->fresh(),
            'next_step' => $this->registrationFollowUpService->nextStep($user),
        ];
    }

    public function skipRegistrationPassword(array $data, string $source = 'web'): array
    {
        $user = isset($data['user_id']) ? Identity::findById((int) $data['user_id']) : auth()->user();

        if (! $user) {
            throw new \Modules\Authentication\Exceptions\InvalidCredentialsException();
        }

        $this->registrationFollowUpService->skipPassword($user);

        return [
            'status' => 'password_skipped',
            'user' => $user->fresh(),
            'next_step' => $this->registrationFollowUpService->nextStep($user),
        ];
    }
}
