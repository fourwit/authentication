<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Identity\Facades\Identity;

class InitializeOtpRegistrationAction
{
    public function __construct(
        protected RegistrationFollowUpService $registrationFollowUpService,
    ) {}

    public function execute(object $user, string $authMethod): void
    {
        if (! $this->registrationFollowUpService->isOtpRegistrationMethod($authMethod)) {
            return;
        }

        Identity::setMetadata($user, RegistrationFollowUpService::META_AUTH_METHOD, $authMethod);
        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_PENDING, (bool) config('authentication.after_otp_registration.prompt_for_password', true));
        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_MISSING, true);
        Identity::forgetMetadata($user, RegistrationFollowUpService::META_FLOW_COMPLETED_AT);
    }
}
