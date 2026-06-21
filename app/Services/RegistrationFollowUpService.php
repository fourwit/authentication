<?php

namespace Modules\Authentication\Services;

use Illuminate\Support\Facades\Hash;
use Modules\Authentication\Support\IdentityUserLookup;
use Modules\Identity\Facades\Identity;

class RegistrationFollowUpService
{
    public const META_AUTH_METHOD = 'registration_auth_method';
    public const META_PASSWORD_PENDING = 'registration_password_pending';
    public const META_PASSWORD_MISSING = 'registration_password_missing';
    public const META_FLOW_COMPLETED_AT = 'registration_flow_completed_at';

    public function initializeForOtpRegistration(object $user, string $authMethod): void
    {
        if (! $this->isOtpRegistrationMethod($authMethod)) {
            return;
        }

        Identity::setMetadata($user, self::META_AUTH_METHOD, $authMethod);
        Identity::setMetadata($user, self::META_PASSWORD_PENDING, (bool) config('authentication.after_otp_registration.prompt_for_password', true));
        Identity::setMetadata($user, self::META_PASSWORD_MISSING, true);
        Identity::forgetMetadata($user, self::META_FLOW_COMPLETED_AT);
    }

    public function isOtpRegistrationMethod(?string $authMethod): bool
    {
        return in_array($authMethod, ['email_otp', 'phone_otp'], true);
    }

    public function registrationMethod(object $user): ?string
    {
        return Identity::getMetadata($user, self::META_AUTH_METHOD);
    }

    public function isPending(object $user): bool
    {
        $method = $this->registrationMethod($user);

        if (! $this->isOtpRegistrationMethod($method)) {
            return false;
        }

        return empty(Identity::getMetadata($user, self::META_FLOW_COMPLETED_AT));
    }

    public function passwordPromptEnabled(): bool
    {
        return (bool) config('authentication.after_otp_registration.prompt_for_password', true);
    }

    public function passwordRequired(): bool
    {
        return (bool) config('authentication.after_otp_registration.password_required', false);
    }

    public function passwordPending(object $user): bool
    {
        return (bool) Identity::getMetadata($user, self::META_PASSWORD_PENDING, false);
    }

    public function passwordMissing(object $user): bool
    {
        return (bool) Identity::getMetadata($user, self::META_PASSWORD_MISSING, false);
    }

    public function verificationChannel(object $user): string
    {
        return $this->registrationMethod($user) === 'phone_otp' ? 'phone' : 'email';
    }

    public function verificationDestination(object $user): string
    {
        return $this->verificationChannel($user) === 'phone'
            ? (string) ($user->phone ?? $user->phone_number ?? $user->identityProfile->phone ?? '')
            : (string) ($user->email ?? '');
    }

    public function nextStep(object $user): string
    {
        if (! $this->isPending($user)) {
            return 'dashboard';
        }

        if ($this->passwordPromptEnabled() && $this->passwordPending($user)) {
            return 'set_password';
        }

        if ((bool) config('authentication.registration.post_verification_profile_completion', false)) {
            return 'profile_completion';
        }

        return 'dashboard';
    }

    public function setPassword(object $user, string $password): void
    {
        Identity::updateUser($user, [
            'password' => Hash::make($password),
        ]);

        Identity::setMetadata($user, self::META_PASSWORD_PENDING, false);
        Identity::setMetadata($user, self::META_PASSWORD_MISSING, false);
    }

    public function skipPassword(object $user): void
    {
        Identity::setMetadata($user, self::META_PASSWORD_PENDING, false);
        Identity::setMetadata($user, self::META_PASSWORD_MISSING, true);
    }

    public function complete(object $user): void
    {
        Identity::setMetadata($user, self::META_FLOW_COMPLETED_AT, now()->toISOString());
    }

    public function clearSessionState(): void
    {
        session()->forget('registration_follow_up_provisional');
    }

    public function markSessionProvisional(object $user): void
    {
        session([
            'registration_follow_up_provisional' => [
                'user_id' => $user->getKey(),
                'auth_method' => $this->registrationMethod($user),
            ],
        ]);
    }

    public function isSessionProvisional(): bool
    {
        return is_array(session('registration_follow_up_provisional'));
    }

    public function resolveUserFromIdentifier(array $data): ?object
    {
        $authMethod = $data['auth_method'] ?? null;

        if ($authMethod === 'phone_otp') {
            return IdentityUserLookup::findByPhone($data['phone'] ?? null);
        }

        return ! empty($data['email']) ? Identity::findByEmail((string) $data['email']) : null;
    }
}
