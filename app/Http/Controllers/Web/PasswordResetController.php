<?php

namespace Modules\Authentication\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Authentication\Exceptions\InvalidPasswordResetTokenException;
use Modules\Authentication\Exceptions\InactiveAccountException;
use Modules\Authentication\Exceptions\SuspendedAccountException;
use Modules\Authentication\Facades\Authentication;
use Modules\Authentication\Http\Requests\ForgotPasswordRequest;
use Modules\Authentication\Http\Requests\ResetPasswordRequest;
use Modules\Authentication\Support\PasswordResetMethodResolver;

class PasswordResetController extends Controller
{
    public function create(Request $request)
    {
        $method = PasswordResetMethodResolver::resolve(
            $request->query('auth_method', old('auth_method'))
        );

        return view("authentication::auth.password-reset.{$this->viewName($method)}", [
            'authMethod' => $method,
            'fieldDefinitions' => PasswordResetMethodResolver::fields($method),
            'requiredFields' => PasswordResetMethodResolver::requiredFields($method),
        ]);
    }

    public function store(ForgotPasswordRequest $request)
    {
        // We deliberately do NOT differentiate the user-facing response based on:
        // - Whether the email exists in the system
        // - Whether email delivery succeeded or failed
        //
        // This is critical to prevent user enumeration attacks.
        // Different messages (or different behavior) for valid vs invalid emails
        // can be used by attackers to discover which emails are registered.
        //
        // The service and notifier still log delivery failures server-side
        // so operations can monitor mailer health.

        try {
            $result = Authentication::sendPasswordReset($request->validated(), 'web');
        } catch (SuspendedAccountException|InactiveAccountException $e) {
            return back()
                ->withInput($request->only('auth_method', 'email', 'phone'))
                ->with('error', $e->getMessage());
        }
        $method = $request->validated('auth_method');

        if (in_array($method, ['email_otp', 'phone_otp'], true)) {
            $resultStatus = $result['status'] ?? 'sent';

            if ($resultStatus === 'rate_limited') {
                return back()
                    ->withInput($request->only('auth_method', 'email', 'phone'))
                    ->with('error', 'Too many password reset code requests were made for this account. Please try again later.');
            }

            session([
                'pending_password_reset' => [
                    'auth_method' => $method,
                    'email' => $request->validated('email'),
                    'phone' => $request->validated('phone'),
                    'channel' => $result['channel'] ?? ($method === 'phone_otp' ? 'phone' : 'email'),
                    'destination' => $result['destination'] ?? ($request->validated('email') ?: $request->validated('phone')),
                ],
            ]);

            $status = match ($resultStatus) {
                'cooldown' => 'A recovery code was recently sent. Enter it below to continue resetting your password.',
                'rate_limited' => 'Too many password reset codes were requested for this account. Please try again later.',
                default => "We've sent a recovery code to " . ($result['destination'] ?? ($request->validated('email') ?: $request->validated('phone'))) . '. Enter it below to continue.',
            };

            if (in_array($resultStatus, ['sent', 'cooldown'], true)) {
                session()->flash('password_reset_code_just_sent', true);
            }

            return redirect()->route('authentication.password.verify')->with('status', $status);
        }

        return back()->with('status', 'If an account with that email exists, a password reset link has been sent. Please check your email.');
    }

    public function edit(Request $request)
    {
        $sessionGrant = session('password_reset_grant');
        if (is_array($sessionGrant) && ! empty($sessionGrant['reset_grant'])) {
            return view('authentication::auth.reset-password', [
                'token' => null,
                'email' => $sessionGrant['email'] ?? null,
                'phone' => $sessionGrant['phone'] ?? null,
                'authMethod' => $sessionGrant['auth_method'] ?? 'email_otp',
                'resetGrant' => $sessionGrant['reset_grant'],
            ]);
        }

        $token = $request->query('token');

        if (empty($token)) {
            return redirect()->route('authentication.password.request')
                ->with('status', 'The password reset link is invalid or has expired.');
        }

        $email = Authentication::getEmailForToken($token);

        if (!$email) {
            return redirect()->route('authentication.password.request')
                ->with('status', 'The password reset link is invalid or has expired.');
        }

        return view('authentication::auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'phone' => null,
            'authMethod' => 'link',
            'resetGrant' => null,
        ]);
    }

    public function update(ResetPasswordRequest $request)
    {
        try {
            $result = Authentication::resetPassword($request->validated(), 'web');
            session()->forget('password_reset_grant');

            if ((bool) config('authentication.password_reset.auto_login_after_reset', false) && isset($result['user'])) {
                auth()->login($result['user']);

                $dashboard = Route::has('dashboard')
                    ? route('dashboard')
                    : (Route::has('home') ? route('home') : url('/'));

                return redirect()->intended($dashboard)->with('status', 'Password reset successfully. You are now signed in.');
            }

            return redirect()->route('authentication.login')->with('status', 'Password reset successfully. You can now sign in.');
        } catch (InvalidPasswordResetTokenException $e) {
            return redirect()->route('authentication.password.request')
                ->with('status', 'The password reset link is invalid or has expired.');
        }
    }

    public function showVerifyForm()
    {
        $pending = session('pending_password_reset');

        if (! is_array($pending) || empty($pending['auth_method'])) {
            return redirect()->route('authentication.password.request')->with('error', 'Please start the password reset process again.');
        }

        $status = [];
        $channel = $pending['channel'] ?? ($pending['auth_method'] === 'phone_otp' ? 'phone' : 'email');
        $destination = $pending['destination'] ?? ($pending['email'] ?? $pending['phone'] ?? 'your account');
        $user = $pending['auth_method'] === 'phone_otp'
            ? \Modules\Authentication\Support\IdentityUserLookup::findByPhone($pending['phone'] ?? null)
            : \Modules\Identity\Facades\Identity::findByEmail((string) ($pending['email'] ?? ''));

        if ($user) {
            $status = app(\Modules\Authentication\Services\VerificationCodeService::class)
                ->getResendStatus($user->id, $channel, 'forgot_password');
        }

        if (session()->pull('password_reset_code_just_sent')) {
            $cooldown = $status['cooldown_seconds'] ?? 60;
            $status['resend_allowed_at'] = now()->addSeconds($cooldown);
        }

        return view('authentication::auth.password-reset.verify', [
            'pendingPasswordReset' => $pending,
            'verificationChannel' => $channel,
            'verificationDestination' => $destination,
            'resendAllowedAt' => $status['resend_allowed_at'] ?? null,
            'cooldownSeconds' => $status['cooldown_seconds'] ?? 60,
        ]);
    }

    public function verify(Request $request)
    {
        $pending = session('pending_password_reset');

        if (! is_array($pending) || empty($pending['auth_method'])) {
            return redirect()->route('authentication.password.request')->with('error', 'Please start the password reset process again.');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:' . (int) config('authentication.otp.length', 6)],
        ]);

        try {
            $result = Authentication::verifyPasswordResetOtp($pending + $validated, 'web');
        } catch (InvalidPasswordResetTokenException $e) {
            return back()->withErrors(['code' => 'Invalid or expired code.']);
        } catch (\Modules\Authentication\Exceptions\MaxVerificationAttemptsExceededException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        session([
            'password_reset_grant' => [
                'auth_method' => $result['auth_method'],
                'email' => $result['email'],
                'phone' => $result['phone'],
                'reset_grant' => $result['reset_grant'],
            ],
        ]);
        session()->forget('pending_password_reset');

        return redirect()->route('authentication.password.reset')->with('status', 'Code verified. Set a new password for your account.');
    }

    public function resendVerifyCode()
    {
        $pending = session('pending_password_reset');

        if (! is_array($pending) || empty($pending['auth_method'])) {
            return redirect()->route('authentication.password.request')->with('error', 'Please start the password reset process again.');
        }

        try {
            $result = Authentication::sendPasswordReset($pending, 'web');
        } catch (SuspendedAccountException|InactiveAccountException $e) {
            return redirect()->route('authentication.password.request')
                ->with('error', $e->getMessage());
        }

        $status = match ($result['status'] ?? 'sent') {
            'cooldown' => 'Please wait a moment before requesting another recovery code.',
            'rate_limited' => 'Too many password reset code requests were made for this account. Please try again later.',
            default => "We've sent a fresh recovery code to " . ($pending['destination'] ?? ($pending['email'] ?: $pending['phone'])) . '.',
        };

        if (in_array($result['status'] ?? 'sent', ['sent', 'cooldown'], true)) {
            session()->flash('password_reset_code_just_sent', true);
        }

        return back()->with('status', $status);
    }

    protected function viewName(string $method): string
    {
        return str_replace('_', '-', $method);
    }
}
