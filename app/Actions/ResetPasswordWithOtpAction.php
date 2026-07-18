<?php

namespace Modules\Authentication\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Authentication\DTOs\ResetPasswordData;
use Modules\Authentication\DTOs\Events\PasswordResetCompletedPayload;
use Modules\Authentication\Events\PasswordResetCompleted;
use Modules\Authentication\Exceptions\InvalidPasswordResetTokenException;
use Modules\Authentication\Repositories\PasswordResetRepository;
use Modules\Authentication\Services\TokenService;
use Modules\Identity\Facades\Identity;

class ResetPasswordWithOtpAction
{
    public function __construct(
        protected PasswordResetRepository $passwordResetRepository,
        protected TokenService $tokenService,
    ) {}

    public function execute(ResetPasswordData $data, string $source = 'web'): array
    {
        $grant = $data->resetGrant ? $this->passwordResetRepository->consumeOtpGrant($data->resetGrant) : null;

        if (! $grant) {
            throw new InvalidPasswordResetTokenException();
        }

        $user = Identity::findById((int) $grant['user_id']);

        if (! $user || ! hash_equals((string) $grant['auth_method'], $data->authMethod)) {
            throw new InvalidPasswordResetTokenException();
        }

        $expectedIdentifier = (string) ($grant['identifier'] ?? '');
        $providedIdentifier = $data->authMethod === 'phone_otp'
            ? (string) ($data->phone ?? '')
            : (string) ($data->email ?? '');

        if ($expectedIdentifier === '' || $providedIdentifier === '' || ! hash_equals($expectedIdentifier, $providedIdentifier)) {
            throw new InvalidPasswordResetTokenException();
        }

        $user->forceFill(['password' => Hash::make($data->password)])->save();

        $response = [
            'status' => 'password_reset',
            'user' => $user,
        ];

        if ((bool) config('authentication.password_reset.auto_login_after_reset', false)) {
            $response['token'] = $this->tokenService->issue($user);
            $response['auto_login'] = true;
        }

        event(new PasswordResetCompleted(PasswordResetCompletedPayload::fromUser($user, $source)));

        return $response;
    }
}
