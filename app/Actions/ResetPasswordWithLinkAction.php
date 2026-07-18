<?php

namespace Modules\Authentication\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Modules\Authentication\DTOs\ResetPasswordData;
use Modules\Authentication\Events\PasswordResetCompleted;
use Modules\Authentication\Exceptions\InvalidPasswordResetTokenException;
use Modules\Authentication\Services\TokenService;
use Modules\Identity\Facades\Identity;

class ResetPasswordWithLinkAction
{
    public function __construct(
        protected TokenService $tokenService,
    ) {}

    public function execute(ResetPasswordData $data, string $source = 'web'): array
    {
        $status = Password::reset([
            'email' => $data->email,
            'password' => $data->password,
            'password_confirmation' => $data->passwordConfirmation ?? $data->password,
            'token' => $data->token,
        ], function ($user) use ($data) {
            $user->forceFill(['password' => Hash::make($data->password)])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw new InvalidPasswordResetTokenException();
        }

        $user = Identity::findByEmail($data->email);
        $response = ['status' => $status, 'user' => $user];

        if ($user && (bool) config('authentication.password_reset.auto_login_after_reset', false)) {
            $response['auto_login'] = true;
            $response['token'] = $this->tokenService->issue($user);
        }

        event(new PasswordResetCompleted($user, $source));

        return $response;
    }
}
