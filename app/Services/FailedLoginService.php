<?php

namespace Modules\Authentication\Services;

use Modules\Authentication\Exceptions\AccountLockedException;
use Modules\Authentication\Repositories\FailedLoginRepository;

class FailedLoginService
{
    public function __construct(protected FailedLoginRepository $repository) {}

    protected function key(string $email): string
    {
        return 'authentication.failed_login.'.sha1(strtolower($email));
    }

    public function record(string $email): int
    {
        $decaySeconds = (int) config('authentication.failed_login.decay_seconds', 900);

        return $this->repository->increment(
            $this->key($email),
            $decaySeconds
        );
    }

    public function ensureNotLocked(string $email): void
    {
        $count = $this->repository->count($this->key($email));
        $maxAttempts = (int) config('authentication.failed_login.max_attempts', 5);

        if ($count >= $maxAttempts) {
            throw new AccountLockedException();
        }
    }

    public function clear(string $email): void
    {
        $this->repository->clear($this->key($email));
    }
}
