<?php

namespace Modules\Authentication\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Authentication\Exceptions\PhoneVerificationNotConfiguredException;
use Modules\Authentication\Facades\Authentication;
use Modules\Authentication\Http\Resources\AuthenticatedUserResource;
use Modules\Authentication\Http\Requests\RegistrationOtpVerifyRequest;
use Modules\Authentication\Http\Requests\SetPasswordRequest;
use Modules\Authentication\Http\Requests\VerifyEmailRequest;

class EmailVerificationController extends Controller
{
    public function send(VerifyEmailRequest $request)
    {
        if (! (bool) config('authentication.verification.enabled', true)) {
            return response()->json(['status' => 'disabled'], 403);
        }

        // Support both old email and new code based
        $data = $request->validated();
        if (isset($data['channel']) || config('authentication.verification.method') === 'code') {
            try {
                return response()->json(Authentication::sendVerificationCode($data + ['user_id' => auth()->id()], 'api'));
            } catch (PhoneVerificationNotConfiguredException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }
        return response()->json(Authentication::sendEmailVerification($data, 'api'));
    }

    public function verify(VerifyEmailRequest $request)
    {
        if (! (bool) config('authentication.verification.enabled', true)) {
            return response()->json(['status' => 'disabled'], 403);
        }

        $data = $request->validated();
        if (isset($data['code']) || config('authentication.verification.method') === 'code') {
            try {
                $result = Authentication::verifyCode($data + ['user_id' => auth()->id()], 'api');
                if (($result['status'] ?? '') === 'verified') {
                    return response()->json($result);
                }
                return response()->json(['status' => 'failed', 'message' => 'Invalid or expired code.'], 422);
            } catch (\Modules\Authentication\Exceptions\MaxVerificationAttemptsExceededException $e) {
                return response()->json(['message' => $e->getMessage(), 'code' => 'MAX_ATTEMPTS'], 422);
            }
        }
        return response()->json(Authentication::verifyEmail($data, 'api'));
    }

    public function resend(VerifyEmailRequest $request)
    {
        if (! (bool) config('authentication.verification.enabled', true)) {
            return response()->json(['status' => 'disabled'], 403);
        }

        $data = $request->validated();
        try {
            return response()->json(Authentication::resendVerificationCode($data + ['user_id' => auth()->id()], 'api'));
        } catch (PhoneVerificationNotConfiguredException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function verifyRegistrationOtp(RegistrationOtpVerifyRequest $request)
    {
        try {
            $result = Authentication::verifyRegistrationOtp($request->validated(), 'api');

            return response()->json([
                'status' => $result['status'],
                'next_step' => $result['next_step'],
                'user' => new AuthenticatedUserResource($result['user']),
            ]);
        } catch (\Modules\Authentication\Exceptions\InvalidCredentialsException $e) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        } catch (\Modules\Authentication\Exceptions\MaxVerificationAttemptsExceededException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function resendRegistrationOtp(RegistrationOtpVerifyRequest $request)
    {
        try {
            return response()->json(Authentication::resendRegistrationOtp($request->validated(), 'api'));
        } catch (\Modules\Authentication\Exceptions\InvalidCredentialsException $e) {
            return response()->json(['message' => 'Unable to resend verification code.'], 422);
        }
    }

    public function setRegistrationPassword(SetPasswordRequest $request)
    {
        $payload = $request->validated() + $request->only('user_id');

        try {
            $result = Authentication::setRegistrationPassword($payload, 'api');

            return response()->json([
                'status' => $result['status'],
                'next_step' => $result['next_step'],
                'user' => new AuthenticatedUserResource($result['user']),
            ]);
        } catch (\Modules\Authentication\Exceptions\InvalidCredentialsException $e) {
            return response()->json(['message' => 'Unable to set password.'], 422);
        }
    }
}
