<?php

use Illuminate\Support\Facades\Route;
use Modules\Authentication\Http\Controllers\Web\AuthController;
use Modules\Authentication\Http\Controllers\Web\PasswordResetController;
use Modules\Authentication\Http\Controllers\Web\RegisterController;

if (in_array(config('authentication.mode', 'both'), ['web', 'both'], true)) {
    Route::prefix(config('authentication.route_prefixes.web', 'auth'))
        ->middleware(config('authentication.middleware.web', ['web']))
        ->group(function () {
        Route::get('/login', [AuthController::class, 'create'])->name('login')->middleware('guest');
        Route::post('/login', [AuthController::class, 'store'])->name('authentication.login')->middleware('guest');
        Route::get('/login/verify', [AuthController::class, 'showLoginOtpVerifyForm'])->name('authentication.login.verify')->middleware('guest');
        Route::post('/login/verify', [AuthController::class, 'verifyLoginOtp'])->name('authentication.login.verify.store')->middleware('guest');
        Route::post('/login/verify/resend', [AuthController::class, 'resendLoginOtp'])->name('authentication.login.verify.resend')->middleware('guest');
        Route::post('/logout', [AuthController::class, 'destroy'])->name('authentication.logout');
        if ((bool) config('authentication.registration.enabled', true)) {
            Route::get('/register', [RegisterController::class, 'create'])->name('authentication.register')->middleware('guest');
            Route::post('/register', [RegisterController::class, 'store'])->name('authentication.register.store')->middleware('guest');
        }
        if ((bool) config('authentication.password_reset.enabled', true)) {
            Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('authentication.password.request');
            Route::post('/forgot-password', [PasswordResetController::class, 'store'])->name('authentication.password.email');
            Route::get('/forgot-password/verify', [PasswordResetController::class, 'showVerifyForm'])->name('authentication.password.verify');
            Route::post('/forgot-password/verify', [PasswordResetController::class, 'verify'])->name('authentication.password.verify.store');
            Route::post('/forgot-password/verify/resend', [PasswordResetController::class, 'resendVerifyCode'])->name('authentication.password.verify.resend');
            Route::get('/reset-password', [PasswordResetController::class, 'edit'])->name('authentication.password.reset');
            Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('authentication.password.update');
        }
        Route::get('/email-verification-notice', [AuthController::class, 'verificationNotice'])->name('authentication.verification.notice');
        Route::get('/verification-result', [AuthController::class, 'verificationResult'])->name('authentication.verification.result');
        Route::get('/account-locked', [AuthController::class, 'accountLocked'])->name('authentication.account.locked');

        // Code based verification (primary flow)
        Route::get('/verify-email', [AuthController::class, 'showVerifyForm'])->name('authentication.verify-email')->middleware('auth');
        Route::post('/verify-email', [AuthController::class, 'verifyCode'])->name('authentication.verify-email.code')->middleware('auth');
        Route::post('/verify-email/resend', [AuthController::class, 'resendVerificationCode'])->name('authentication.verify-email.resend')->middleware('auth');
        Route::get('/me', [AuthController::class, 'me'])->name('authentication.me')->middleware('auth');
        Route::get('/set-password', [AuthController::class, 'showSetPasswordForm'])->name(config('authentication.after_otp_registration.password_setup_route', 'auth.set-password'))->middleware(['auth', 'auth.registration.complete']);
        Route::post('/set-password', [AuthController::class, 'storeSetPassword'])->name(config('authentication.after_otp_registration.password_setup_route', 'auth.set-password') . '.store')->middleware(['auth', 'auth.registration.complete']);
        Route::post('/set-password/skip', [AuthController::class, 'skipSetPassword'])->name(config('authentication.after_otp_registration.password_setup_route', 'auth.set-password') . '.skip')->middleware(['auth', 'auth.registration.complete']);

        // Verification required (used by auth.verified middleware redirect)
        Route::get('/verification-required', [AuthController::class, 'verificationRequired'])->name('authentication.verification.required')->middleware('auth');
    });
}
