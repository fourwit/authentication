<?php

namespace Modules\Authentication\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Authentication\Exceptions\InactiveAccountException;
use Modules\Authentication\Exceptions\InvalidPasswordResetTokenException;
use Modules\Authentication\Exceptions\SuspendedAccountException;
use Modules\Authentication\Facades\Authentication;
use Modules\Authentication\Http\Requests\ForgotPasswordRequest;
use Modules\Authentication\Http\Requests\ResetPasswordRequest;
use Modules\Authentication\Http\Requests\VerifyPasswordResetOtpRequest;

class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request)
    {
        // Always return a uniform response for security.
        // Never reveal whether the identifier exists or whether delivery succeeded.
        try {
            $result = Authentication::sendPasswordReset($request->validated(), 'api');
        } catch (SuspendedAccountException|InactiveAccountException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if (in_array($request->validated('auth_method'), ['email_otp', 'phone_otp'], true)) {
            $status = $result['status'] === 'rate_limited' ? 'rate_limited' : 'otp_sent';

            return response()->json([
                'status' => $status,
                'auth_method' => $request->validated('auth_method'),
                'channel' => $result['channel'] ?? null,
                'destination' => $result['destination'] ?? null,
                'message' => $status === 'rate_limited'
                    ? 'Too many password reset code requests were made for this account. Please wait before trying again.'
                    : 'If an account matches that identifier, a recovery code has been sent.',
            ], $status === 'rate_limited' ? 429 : 202);
        }

        return response()->json([
            'status' => 'passwords.sent',
            'message' => 'If an account with that email exists, a password reset link has been sent.',
        ]);
    }

    public function verifyOtp(VerifyPasswordResetOtpRequest $request)
    {
        try {
            $result = Authentication::verifyPasswordResetOtp($request->validated(), 'api');
        } catch (InvalidPasswordResetTokenException $e) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        } catch (\Modules\Authentication\Exceptions\MaxVerificationAttemptsExceededException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'verified',
            'next_step' => 'set_password',
            'reset_grant' => $result['reset_grant'],
            'auth_method' => $result['auth_method'],
            'email' => $result['email'],
            'phone' => $result['phone'],
        ]);
    }

    public function reset(ResetPasswordRequest $request)
    {
        try {
            return response()->json(Authentication::resetPassword($request->validated(), 'api'));
        } catch (InvalidPasswordResetTokenException $e) {
            return response()->json(['message' => 'The password reset request is invalid or has expired.'], 422);
        }
    }
}
