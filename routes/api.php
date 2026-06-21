<?php

use Illuminate\Support\Facades\Route;
use Modules\Authentication\Http\Controllers\Api\AuthController;
use Modules\Authentication\Http\Controllers\Api\EmailVerificationController;
use Modules\Authentication\Http\Controllers\Api\PasswordResetController;
use Modules\Authentication\Http\Controllers\Api\RegisterController;

if (in_array(config('authentication.mode', 'both'), ['api', 'both'], true)) {
    $apiAuthMiddleware = 'auth:' . config('authentication.guards.api', 'sanctum');

    Route::prefix(config('authentication.route_prefixes.api', 'api/v1/auth'))
        ->middleware(config('authentication.middleware.api', ['api']))
        ->group(function () use ($apiAuthMiddleware) {
        if ((bool) config('authentication.registration.enabled', true)) {
            Route::post('/register', [RegisterController::class, 'store']);
        }
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/login/verify', [AuthController::class, 'verifyLoginOtp']);
        Route::post('/login/verify/resend', [AuthController::class, 'resendLoginOtp']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware($apiAuthMiddleware);
        if ((bool) config('authentication.password_reset.enabled', true)) {
            Route::post('/forgot-password', [PasswordResetController::class, 'forgot']);
            Route::post('/forgot-password/verify', [PasswordResetController::class, 'verifyOtp']);
            Route::post('/reset-password', [PasswordResetController::class, 'reset']);
        }
        // Code based verification
        Route::post('/verification/send', [EmailVerificationController::class, 'send'])->middleware($apiAuthMiddleware);
        Route::post('/verification/verify', [EmailVerificationController::class, 'verify'])->middleware($apiAuthMiddleware);
        Route::post('/verification/resend', [EmailVerificationController::class, 'resend'])->middleware($apiAuthMiddleware);
        Route::post('/register/verify', [EmailVerificationController::class, 'verifyRegistrationOtp']);
        Route::post('/register/verify/resend', [EmailVerificationController::class, 'resendRegistrationOtp']);
        Route::post('/register/set-password', [EmailVerificationController::class, 'setRegistrationPassword']);

        // Legacy email
        Route::post('/email/verification/send', [EmailVerificationController::class, 'send'])->middleware($apiAuthMiddleware);
        Route::post('/email/verification/verify', [EmailVerificationController::class, 'verify'])->middleware($apiAuthMiddleware);
        // Example protected route with verification enforcement (post-login)
        Route::get('/me', [AuthController::class, 'me'])->middleware([$apiAuthMiddleware, 'auth.verified']);
    });
}
