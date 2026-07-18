<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Exceptions\InvalidRegistrationGrantException;
use Modules\Authentication\Repositories\RegistrationGrantRepository;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Identity\Facades\Identity;

class ResolveRegistrationCompletionUserAction
{
    public function __construct(
        protected RegistrationFollowUpService $registrationFollowUpService,
        protected RegistrationGrantRepository $registrationGrantRepository,
    ) {}

    public function execute(array $data): object
    {
        if (auth()->check()) {
            $user = auth()->user();

            if ($user && (
                $this->registrationFollowUpService->isPending($user)
                || $this->registrationFollowUpService->passwordPending($user)
                || $this->registrationFollowUpService->passwordMissing($user)
            )) {
                return $user;
            }

            throw new InvalidCredentialsException();
        }

        if (! empty($data['user_id'])) {
            throw new InvalidRegistrationGrantException();
        }

        $grant = (string) ($data['registration_grant'] ?? '');
        if ($grant === '') {
            throw new InvalidRegistrationGrantException();
        }

        $grantData = $this->registrationGrantRepository->consumeGrantForCompletion($grant);
        if (! $grantData) {
            throw new InvalidRegistrationGrantException();
        }

        $user = Identity::findById((int) $grantData['user_id']);
        if (! $user) {
            throw new InvalidRegistrationGrantException();
        }

        return $user;
    }
}
