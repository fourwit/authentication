<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Services\VerificationCodeService;

class ResendVerificationCode
{
    public function __construct(protected VerificationCodeService $service) {}

    public function execute(int $userId, string $channel, string $source = 'web'): array
    {
        return $this->service->resendCode($userId, $channel, $source);
    }
}
