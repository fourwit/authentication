<?php

namespace Modules\Authentication\Repositories;

use Illuminate\Contracts\Auth\Authenticatable;

class AuthTokenRepository
{
    public function revokeCurrentToken(?Authenticatable $user): void
    {
        if (! $user || ! method_exists($user, 'currentAccessToken')) {
            return;
        }

        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }
    }
}
