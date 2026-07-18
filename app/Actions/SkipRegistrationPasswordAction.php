<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Identity\Facades\Identity;

class SkipRegistrationPasswordAction
{
    public function __construct(
        protected ResolveRegistrationCompletionUserAction $resolveRegistrationCompletionUserAction,
        protected RegistrationFollowUpService $registrationFollowUpService,
    ) {}

    public function execute(array $data, string $source = 'web'): array
    {
        $user = $this->resolveRegistrationCompletionUserAction->execute($data);

        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_PENDING, false);
        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_MISSING, true);

        $user = $user->fresh();

        return [
            'status' => 'password_skipped',
            'user' => $user,
            'next_step' => $this->registrationFollowUpService->nextStep($user),
        ];
    }
}
