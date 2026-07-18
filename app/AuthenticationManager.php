<?php

namespace Modules\Authentication;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Modules\Authentication\DTOs\EmailVerificationData;
use Modules\Authentication\DTOs\LoginData;
use Modules\Authentication\DTOs\PasswordResetRequestData;
use Modules\Authentication\DTOs\RegisterUserData;
use Modules\Authentication\DTOs\ResetPasswordData;
use Modules\Authentication\Actions\LogoutUserAction;
use Modules\Authentication\Actions\ResendLoginOtpAction;
use Modules\Authentication\Actions\ResendRegistrationOtpAction;
use Modules\Authentication\Actions\ResendVerificationCode;
use Modules\Authentication\Actions\SendVerificationCode;
use Modules\Authentication\Actions\SetRegistrationPasswordAction;
use Modules\Authentication\Actions\SkipRegistrationPasswordAction;
use Modules\Authentication\Actions\VerifyEmailVerificationCodeAction;
use Modules\Authentication\Actions\VerifyLoginOtpAction;
use Modules\Authentication\Actions\VerifyRegistrationOtpAction;
use Modules\Authentication\Services\EmailVerificationService;
use Modules\Authentication\Services\LoginService;
use Modules\Authentication\Services\PasswordResetService;
use Modules\Authentication\Services\RegistrationService;
use Modules\Authentication\Support\VerificationConfig;

class AuthenticationManager
{
    public function __construct(
        protected LoginService $loginService,
        protected RegistrationService $registrationService,
        protected PasswordResetService $passwordResetService,
        protected EmailVerificationService $emailVerificationService,
        protected SendVerificationCode $sendVerificationCodeAction,
        protected ResendVerificationCode $resendVerificationCodeAction,
        protected VerifyEmailVerificationCodeAction $verifyEmailVerificationCodeAction,
        protected LogoutUserAction $logoutUserAction,
        protected VerifyLoginOtpAction $verifyLoginOtpAction,
        protected ResendLoginOtpAction $resendLoginOtpAction,
        protected VerifyRegistrationOtpAction $verifyRegistrationOtpAction,
        protected ResendRegistrationOtpAction $resendRegistrationOtpAction,
        protected SetRegistrationPasswordAction $setRegistrationPasswordAction,
        protected SkipRegistrationPasswordAction $skipRegistrationPasswordAction,
    ) {}

    public function login(array $data, string $source = 'web'): array
    {
        return $this->loginService->login(LoginData::fromArray($data), $source);
    }

    public function verifyLoginOtp(array $data, string $source = 'web'): array
    {
        return $this->verifyLoginOtpAction->execute(
            LoginData::fromArray($data),
            (string) ($data['code'] ?? ''),
            $source
        );
    }

    public function resendLoginOtp(array $data, string $source = 'web'): array
    {
        return $this->resendLoginOtpAction->execute(LoginData::fromArray($data), $source);
    }

    public function logout($user = null, string $source = 'web'): void
    {
        $this->logoutUserAction->execute($user, $source);
    }

    public function register(array $data, string $source = 'web'): array
    {
        return $this->registrationService->register(RegisterUserData::fromArray($data), $source);
    }

    public function user($request = null): ?Authenticatable
    {
        $request = $request instanceof Request ? $request : request();
        return $request->user();
    }

    public function sendPasswordReset(array $data, string $source = 'web'): array
    {
        return $this->passwordResetService->sendResetLink(
            PasswordResetRequestData::fromArray($data),
            $source
        );
    }

    public function resetPassword(array $data, string $source = 'web'): array
    {
        return $this->passwordResetService->reset(
            ResetPasswordData::fromArray($data),
            $source
        );
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
        return $this->emailVerificationService->send(
            EmailVerificationData::fromArray($data),
            $source
        );
    }

    public function verifyEmail(array $data, string $source = 'web'): array
    {
        return $this->emailVerificationService->verify(
            EmailVerificationData::fromArray($data),
            $source
        );
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

        return $this->verifyEmailVerificationCodeAction->execute($userId, $channel, $code, $source);
    }

    public function resendVerificationCode(array $data, string $source = 'web'): array
    {
        $userId = $data['user_id'] ?? auth()->id();
        $channel = $data['channel'] ?? VerificationConfig::ensureSupportedChannel();

        return $this->resendVerificationCodeAction->execute($userId, $channel, $source);
    }

    public function verifyRegistrationOtp(array $data, string $source = 'web'): array
    {
        return $this->verifyRegistrationOtpAction->execute($data, $source);
    }

    public function resendRegistrationOtp(array $data, string $source = 'web'): array
    {
        return $this->resendRegistrationOtpAction->execute($data, $source);
    }

    public function setRegistrationPassword(array $data, string $source = 'web'): array
    {
        return $this->setRegistrationPasswordAction->execute($data, $source);
    }

    public function skipRegistrationPassword(array $data, string $source = 'web'): array
    {
        return $this->skipRegistrationPasswordAction->execute($data, $source);
    }
}
