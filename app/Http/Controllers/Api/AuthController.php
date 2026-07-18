<?php

namespace Modules\Authentication\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Authentication\Facades\Authentication;
use Modules\Authentication\Http\Requests\LoginRequest;
use Modules\Authentication\Http\Requests\ResendLoginOtpRequest;
use Modules\Authentication\Http\Requests\VerifyLoginOtpRequest;
use Modules\Authentication\Http\Resources\AuthenticatedSessionResource;
use Modules\Authentication\Http\Resources\AuthenticatedUserResource;
use Modules\Authentication\Http\Resources\LoginOtpResendResource;
use Modules\Authentication\Http\Resources\LoginOtpSentResource;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        try {
            $result = Authentication::login($request->validated(), 'api');
        } catch (\Modules\Authentication\Exceptions\AccountLockedException $e) {
            return response()->json(['message' => 'Account is locked due to too many failed attempts.'], 423);
        } catch (\Modules\Authentication\Exceptions\SuspendedAccountException|\Modules\Authentication\Exceptions\InactiveAccountException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Modules\Authentication\Exceptions\InvalidCredentialsException $e) {
            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        if (($result['status'] ?? null) && in_array($request->validated('auth_method'), ['email_otp', 'phone_otp'], true)) {
            return new LoginOtpSentResource($result);
        }

        return new AuthenticatedSessionResource($result);
    }

    public function verifyLoginOtp(VerifyLoginOtpRequest $request)
    {
        try {
            $result = Authentication::verifyLoginOtp($request->validated(), 'api');
        } catch (\Modules\Authentication\Exceptions\AccountLockedException $e) {
            return response()->json(['message' => 'Account is locked due to too many failed attempts.'], 423);
        } catch (\Modules\Authentication\Exceptions\SuspendedAccountException|\Modules\Authentication\Exceptions\InactiveAccountException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Modules\Authentication\Exceptions\InvalidCredentialsException $e) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        } catch (\Modules\Authentication\Exceptions\MaxVerificationAttemptsExceededException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AuthenticatedSessionResource($result);
    }

    public function resendLoginOtp(ResendLoginOtpRequest $request)
    {
        try {
            $result = Authentication::resendLoginOtp($request->validated(), 'api');
        } catch (\Modules\Authentication\Exceptions\SuspendedAccountException|\Modules\Authentication\Exceptions\InactiveAccountException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Modules\Authentication\Exceptions\InvalidCredentialsException $e) {
            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        return new LoginOtpResendResource($result);
    }

    public function logout()
    {
        Authentication::logout(auth()->user(), 'api');
        return response()->noContent();
    }

    public function me()
    {
        return new AuthenticatedUserResource(Authentication::user());
    }
}
