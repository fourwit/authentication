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
use Modules\Authentication\Http\Resources\PasswordResetCompletedResource;
use Modules\Authentication\Http\Resources\PasswordResetInitiatedResource;
use Modules\Authentication\Http\Resources\PasswordResetOtpVerifiedResource;

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
            return new PasswordResetInitiatedResource($result + [
                'auth_method' => $request->validated('auth_method'),
            ]);
        }

        return new PasswordResetInitiatedResource(['flow' => 'link']);
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

        return new PasswordResetOtpVerifiedResource($result);
    }

    public function reset(ResetPasswordRequest $request)
    {
        try {
            return new PasswordResetCompletedResource(
                Authentication::resetPassword($request->validated(), 'api')
            );
        } catch (InvalidPasswordResetTokenException $e) {
            return response()->json(['message' => 'The password reset request is invalid or has expired.'], 422);
        }
    }
}
