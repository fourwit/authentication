<?php

namespace Modules\Authentication\Actions;

use Modules\Authentication\Exceptions\InvalidPasswordResetTokenException;
use Modules\Authentication\Repositories\PasswordResetRepository;
use Modules\Authentication\Services\VerificationCodeService;
use Modules\Authentication\Support\PasswordResetCredentialResolver;

class VerifyPasswordResetOtpAction
{
    public function __construct(
        protected VerificationCodeService $verificationCodeService,
        protected PasswordResetRepository $passwordResetRepository,
    ) {}

    public function execute(array $data, string $source = 'web'): array
    {
        $authMethod = (string) ($data['auth_method'] ?? 'email_otp');
        $identifier = PasswordResetCredentialResolver::identifierFor($data, $authMethod);
        $user = PasswordResetCredentialResolver::resolveUser($identifier, $authMethod);

        if (! $user || ! $identifier) {
            throw new InvalidPasswordResetTokenException();
        }

        $channel = PasswordResetCredentialResolver::channelFor($authMethod);
        $verified = $this->verificationCodeService->verifyCode(
            $user->id,
            $channel,
            (string) ($data['code'] ?? ''),
            $source,
            'forgot_password'
        );

        if (! $verified) {
            throw new InvalidPasswordResetTokenException();
        }

        $grant = $this->passwordResetRepository->createOtpGrant($user->id, $authMethod, (string) $identifier);

        return [
            'status' => 'verified',
            'user' => $user,
            'reset_grant' => $grant,
            'auth_method' => $authMethod,
            'email' => $authMethod === 'email_otp' ? (string) $identifier : null,
            'phone' => $authMethod === 'phone_otp' ? (string) $identifier : null,
            'next_step' => 'set_password',
        ];
    }
}
