<?php

namespace Modules\Authentication\Repositories;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetRepository
{
    protected string $otpGrantPrefix = 'authentication.password_reset_grant.';

    public function sendResetLink(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }

    public function reset(array $payload): string
    {
        return Password::reset($payload);
    }

    public function createOtpGrant(int $userId, string $authMethod, string $identifier): string
    {
        $token = (string) Str::uuid();
        $ttl = now()->addMinutes((int) config('authentication.otp.expires_minutes', 10));

        Cache::put($this->otpGrantKey($token), [
            'user_id' => $userId,
            'auth_method' => $authMethod,
            'identifier' => $identifier,
        ], $ttl);

        return $token;
    }

    public function getOtpGrant(string $token): ?array
    {
        $grant = Cache::get($this->otpGrantKey($token));

        return is_array($grant) ? $grant : null;
    }

    public function consumeOtpGrant(string $token): ?array
    {
        $grant = $this->getOtpGrant($token);

        if ($grant) {
            Cache::forget($this->otpGrantKey($token));
        }

        return $grant;
    }

    protected function otpGrantKey(string $token): string
    {
        return $this->otpGrantPrefix.$token;
    }
}
