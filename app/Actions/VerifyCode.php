<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Services\VerificationCodeService;

class VerifyCode
{
    public function __construct(protected VerificationCodeService $service) {}

    public function execute(int $userId, string $channel, string $plainCode, string $source = 'web'): bool
    {
        return $this->service->verifyCode($userId, $channel, $plainCode, $source);
    }
}
