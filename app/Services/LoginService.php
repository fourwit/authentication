<?php

namespace Modules\Authentication\Services;

use Modules\Authentication\Actions\InitiateLoginOtpAction;
use Modules\Authentication\Actions\LoginUserAction;
use Modules\Authentication\Actions\SendLoginVerificationCodeAction;
use Modules\Authentication\DTOs\LoginData;
use Modules\Authentication\Support\LoginCredentialResolver;

class LoginService
{
    public function __construct(
        protected FailedLoginService $failedLoginService,
        protected LoginUserAction $loginUserAction,
        protected InitiateLoginOtpAction $initiateLoginOtpAction,
        protected SendLoginVerificationCodeAction $sendLoginVerificationCodeAction,
    ) {}

    public function login(LoginData $data, string $source = 'web'): array
    {
        $identifier = LoginCredentialResolver::identifier($data);
        $this->failedLoginService->ensureNotLocked($identifier);

        if ($data->authMethod === 'email_password') {
            $result = $this->loginUserAction->execute($data, $source);
            $this->sendLoginVerificationCodeAction->execute($result['user'], $source);

            return $result;
        }

        return $this->initiateLoginOtpAction->execute($data, $source);
    }
}
