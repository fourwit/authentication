<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Services\VerificationCodeService;

class SendVerificationCode
{
    public function __construct(protected VerificationCodeService $service) {}

    public function execute(int $userId, string $channel, string $source = 'web'): array
    {
        return $this->service->sendCode($userId, $channel, $source);
    }
}
