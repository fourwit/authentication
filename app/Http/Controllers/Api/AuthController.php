<?php

namespace Modules\Authentication\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Facades\Authentication;
use Modules\Authentication\Exceptions\UnsupportedLoginMethodException;
use Modules\Authentication\Http\Requests\LoginRequest;
use Modules\Authentication\Http\Resources\AuthenticatedUserResource;
use Modules\Authentication\Http\Resources\TokenResource;

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
            $status = $result['status'] === 'rate_limited' ? 'rate_limited' : 'otp_sent';

            return response()->json([
                'status' => $status,
                'channel' => $result['channel'],
                'destination' => $result['destination'],
                'expires_at' => $result['expires_at'],
                'message' => $status === 'rate_limited'
                    ? 'Too many login code requests were made for this account. Please wait before trying again.'
                    : null,
            ], $status === 'rate_limited' ? 429 : 202);
        }

        return response()->json([
            'user' => new AuthenticatedUserResource($result['user']),
            'token' => new TokenResource($result),
        ]);
    }

    public function verifyLoginOtp(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'auth_method' => ['required', 'string', 'in:email_otp,phone_otp'],
            'email' => ['nullable', 'email', 'required_if:auth_method,email_otp'],
            'phone' => ['nullable', 'string', 'required_if:auth_method,phone_otp'],
            'code' => ['required', 'string', 'size:' . (int) config('authentication.otp.length', 6)],
        ]);

        try {
            $result = Authentication::verifyLoginOtp($validated, 'api');
        } catch (\Modules\Authentication\Exceptions\AccountLockedException $e) {
            return response()->json(['message' => 'Account is locked due to too many failed attempts.'], 423);
        } catch (\Modules\Authentication\Exceptions\SuspendedAccountException|\Modules\Authentication\Exceptions\InactiveAccountException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Modules\Authentication\Exceptions\InvalidCredentialsException $e) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        } catch (\Modules\Authentication\Exceptions\MaxVerificationAttemptsExceededException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'user' => new AuthenticatedUserResource($result['user']),
            'token' => new TokenResource($result),
        ]);
    }

    public function resendLoginOtp(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'auth_method' => ['required', 'string', 'in:email_otp,phone_otp'],
            'email' => ['nullable', 'email', 'required_if:auth_method,email_otp'],
            'phone' => ['nullable', 'string', 'required_if:auth_method,phone_otp'],
        ]);

        try {
            $result = Authentication::resendLoginOtp($validated, 'api');
        } catch (\Modules\Authentication\Exceptions\SuspendedAccountException|\Modules\Authentication\Exceptions\InactiveAccountException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Modules\Authentication\Exceptions\InvalidCredentialsException $e) {
            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        return response()->json([
            'status' => $result['status'] ?? 'sent',
            'channel' => $result['channel'] ?? null,
        ]);
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
