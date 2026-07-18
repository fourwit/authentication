<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Repositories\RegistrationGrantRepository;

class IssueRegistrationGrantAction
{
    public function __construct(
        protected RegistrationGrantRepository $registrationGrantRepository,
    ) {}

    public function execute(int $userId, string $authMethod, bool $verified = false): string
    {
        return $this->registrationGrantRepository->createGrant($userId, $authMethod, $verified);
    }
}
