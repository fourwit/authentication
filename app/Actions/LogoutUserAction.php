<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Events\UserLoggedOut;
use Modules\Authentication\Services\TokenService;

class LogoutUserAction
{
    public function __construct(
        protected TokenService $tokenService,
    ) {}

    public function execute($user = null, string $source = 'web'): void
    {
        try {
            $this->tokenService->revokeCurrentToken($user);
        } catch (\Throwable) {
            return;
        }

        event(new UserLoggedOut($user, $source));
    }
}
