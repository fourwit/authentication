<?php

namespace Modules\Authentication\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Identity\Facades\Identity;

class SetRegistrationPasswordAction
{
    public function __construct(
        protected ResolveRegistrationCompletionUserAction $resolveRegistrationCompletionUserAction,
        protected RegistrationFollowUpService $registrationFollowUpService,
    ) {}

    public function execute(array $data, string $source = 'web'): array
    {
        $user = $this->resolveRegistrationCompletionUserAction->execute($data);

        Identity::updateUser($user, [
            'password' => Hash::make((string) $data['password']),
        ]);

        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_PENDING, false);
        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_MISSING, false);

        $user = $user->fresh();

        return [
            'status' => 'password_set',
            'user' => $user,
            'next_step' => $this->registrationFollowUpService->nextStep($user),
        ];
    }
}
