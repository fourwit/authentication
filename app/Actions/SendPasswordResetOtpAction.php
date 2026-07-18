<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\DTOs\PasswordResetRequestData;
use Modules\Authentication\Events\PasswordResetRequested;
use Modules\Authentication\Services\VerificationCodeService;
use Modules\Authentication\Support\AccountStatusGate;
use Modules\Authentication\Support\IdentityUserLookup;
use Modules\Identity\Facades\Identity;

class SendPasswordResetOtpAction
{
    public function __construct(
        protected VerificationCodeService $verificationCodeService,
    ) {}

    public function execute(PasswordResetRequestData $data, string $source = 'web'): array
    {
        $channel = $data->authMethod === 'phone_otp' ? 'phone' : 'email';
        $identifier = $channel === 'phone' ? $data->phone : $data->email;
        $user = $channel === 'phone'
            ? IdentityUserLookup::findByPhone($data->phone)
            : Identity::findByEmail((string) $data->email);

        if (! $user || ! $identifier) {
            event(new PasswordResetRequested($data->email ?? $data->phone ?? '', $source));

            return [
                'status' => 'passwords.sent',
                'auth_method' => $data->authMethod,
                'channel' => $channel,
                'destination' => $identifier,
            ];
        }

        AccountStatusGate::allowPasswordReset($user);

        $result = $this->verificationCodeService->sendCode(
            $user->id,
            $channel,
            $source,
            true,
            false,
            'forgot_password'
        );

        event(new PasswordResetRequested($data->email ?? $data->phone ?? '', $source));

        return $result + [
            'auth_method' => $data->authMethod,
            'channel' => $channel,
            'destination' => $result['destination'] ?? $identifier,
            'user' => $user,
        ];
    }
}
