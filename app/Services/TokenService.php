<?php

namespace Modules\Authentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;

class TokenService
{
    public function issue(Authenticatable $user, string $name = 'auth'): ?string
    {
        return $this->issueWithExpiry($user, $name)['token'];
    }

    public function issueForLogin(Authenticatable $user, bool $remember = false, string $source = 'web', string $name = 'auth'): array
    {
        if ($source !== 'api') {
            return $this->issueWithExpiry($user, $name);
        }

        $minutes = $remember
            ? config('authentication.login.api_tokens.remember_expires_minutes')
            : config('authentication.login.api_tokens.expires_minutes');

        return $this->issueWithExpiry($user, $name, $this->resolveExpiry($minutes));
    }

    protected function issueWithExpiry(Authenticatable $user, string $name = 'auth', ?Carbon $expiresAt = null): array
    {
        if ((string) config('authentication.token_driver', 'sanctum') !== 'sanctum') {
            return ['token' => null, 'expires_at' => null];
        }

        if (! method_exists($user, 'createToken')) {
            return ['token' => null, 'expires_at' => null];
        }

        $tokenResult = $user->createToken($name, ['*'], $expiresAt);

        return [
            'token' => $tokenResult->plainTextToken,
            'expires_at' => $expiresAt?->toISOString(),
        ];
    }

    protected function resolveExpiry(mixed $minutes): ?Carbon
    {
        if ($minutes === null || $minutes === '') {
            return null;
        }

        $minutes = (int) $minutes;

        return $minutes > 0 ? now()->addMinutes($minutes) : null;
    }

    public function revokeCurrentToken(?Authenticatable $user): void
    {
        if ((string) config('authentication.token_driver', 'sanctum') !== 'sanctum') {
            return;
        }

        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }
    }
}
