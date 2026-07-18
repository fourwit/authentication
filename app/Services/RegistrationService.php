<?php

namespace Modules\Authentication\Services;

use Modules\Authentication\Actions\InitializeOtpRegistrationAction;
use Modules\Authentication\Actions\IssueRegistrationGrantAction;
use Modules\Authentication\Actions\RegisterUserAction;
use Modules\Authentication\Actions\SendRegistrationVerificationCodeAction;
use Modules\Authentication\DTOs\RegisterUserData;

class RegistrationService
{
    public function __construct(
        protected RegisterUserAction $registerUserAction,
        protected InitializeOtpRegistrationAction $initializeOtpRegistrationAction,
        protected IssueRegistrationGrantAction $issueRegistrationGrantAction,
        protected SendRegistrationVerificationCodeAction $sendRegistrationVerificationCodeAction,
        protected RegistrationFollowUpService $registrationFollowUpService,
    ) {}

    public function register(RegisterUserData $data, string $source = 'web'): array
    {
        $registration = $this->registerUserAction->execute($data, $source);
        $user = $registration['user'];
        $reusedUnverified = (bool) ($registration['reused_unverified'] ?? false);

        if ($this->registrationFollowUpService->isOtpRegistrationMethod($data->authMethod)) {
            $this->initializeOtpRegistrationAction->execute($user, $data->authMethod);
        }

        $registrationGrant = null;
        if ($this->registrationFollowUpService->isOtpRegistrationMethod($data->authMethod)) {
            $registrationGrant = $this->issueRegistrationGrantAction->execute(
                (int) $user->id,
                $data->authMethod,
                verified: false
            );
        }

        $this->sendRegistrationVerificationCodeAction->execute(
            $user,
            $data->authMethod,
            $reusedUnverified,
            $source
        );

        return [
            'user' => $user,
            'was_created' => (bool) ($registration['was_created'] ?? false),
            'reused_unverified' => $reusedUnverified,
            'registration_grant' => $registrationGrant,
        ];
    }
}
