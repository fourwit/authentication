<?php

namespace Modules\Authentication\Repositories;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RegistrationGrantRepository
{
    protected string $grantPrefix = 'authentication.registration_grant.';

    public function createGrant(int $userId, string $authMethod, bool $verified = false): string
    {
        $token = (string) Str::uuid();
        $ttl = now()->addMinutes($this->expiresMinutes());

        Cache::put($this->grantKey($token), [
            'user_id' => $userId,
            'auth_method' => $authMethod,
            'verified' => $verified,
        ], $ttl);

        return $token;
    }

    public function getGrant(string $token): ?array
    {
        $grant = Cache::get($this->grantKey($token));

        return is_array($grant) ? $grant : null;
    }

    /**
     * Consume a grant that has been authorized for registration completion (password setup).
     *
     * Only verified grants are removed from cache. Unverified grants are rejected without
     * being consumed so the registration flow can continue.
     *
     * Uses a per-grant lock so concurrent requests cannot consume the same grant twice.
     */
    public function consumeGrantForCompletion(string $token): ?array
    {
        $grantKey = $this->grantKey($token);
        $lock = Cache::lock($grantKey.':consume', 10);

        try {
            return $lock->block(5, function () use ($grantKey) {
                $grant = Cache::get($grantKey);

                if (! is_array($grant) || empty($grant['verified'])) {
                    return null;
                }

                Cache::forget($grantKey);

                return $grant;
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return null;
        }
    }

    protected function grantKey(string $token): string
    {
        return $this->grantPrefix.$token;
    }

    protected function expiresMinutes(): int
    {
        return (int) config(
            'authentication.after_otp_registration.registration_grant_expires_minutes',
            60
        );
    }
}
