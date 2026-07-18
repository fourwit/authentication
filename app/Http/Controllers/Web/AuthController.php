<?php

namespace Modules\Authentication\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Exceptions\PhoneVerificationNotConfiguredException;
use Modules\Authentication\Facades\Authentication;
use Modules\Authentication\Http\Requests\LoginRequest;
use Modules\Authentication\Http\Requests\OtpCodeRequest;
use Modules\Authentication\Http\Requests\ResendVerificationCodeRequest;
use Modules\Authentication\Http\Requests\SetPasswordRequest;
use Modules\Authentication\Http\Requests\VerifyVerificationCodeRequest;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Authentication\Support\LoginMethodResolver;

class AuthController extends Controller
{
    public function __construct(
        protected RegistrationFollowUpService $registrationFollowUpService,
    ) {}

    public function create(Request $request)
    {
        if (auth()->check()) {
            $target = Route::has('dashboard')
                ? route('dashboard')
                : (Route::has('home') ? route('home') : url('/'));
            return redirect()->intended($target);
        }

        $method = LoginMethodResolver::resolve(
            $request->query('auth_method', old('auth_method'))
        );

        return view("authentication::auth.login.{$this->viewName($method)}", [
            'authMethod' => $method,
            'fieldDefinitions' => LoginMethodResolver::fields($method),
            'requiredFields' => LoginMethodResolver::requiredFields($method),
            'switchLinks' => LoginMethodResolver::switchLinksFor($method),
        ]);
    }

    public function store(LoginRequest $request)
    {
        try {
            $result = Authentication::login($request->validated(), 'web');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Modules\Authentication\Exceptions\AccountLockedException $e) {
            return redirect()->route('authentication.account.locked');
        } catch (\Modules\Authentication\Exceptions\SuspendedAccountException|\Modules\Authentication\Exceptions\InactiveAccountException $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput($request->only('auth_method', 'email', 'phone', 'remember'));
        } catch (\Modules\Authentication\Exceptions\InvalidCredentialsException $e) {
            // Use top-level session error (not attached to a specific field)
            // so that neither email nor password field gets error/dirty styling.
            return back()
                ->with('error', 'These credentials do not match our records.')
                ->withInput($request->only('auth_method', 'email', 'phone', 'remember'));
        }

        if (($result['status'] ?? null) && in_array($request->validated('auth_method'), ['email_otp', 'phone_otp'], true)) {
            session([
                'pending_login_otp' => [
                    'auth_method' => $request->validated('auth_method'),
                    'email' => $request->validated('email'),
                    'phone' => $request->validated('phone'),
                    'channel' => $result['channel'] ?? ($request->validated('auth_method') === 'phone_otp' ? 'phone' : 'email'),
                    'destination' => $result['destination'] ?? ($request->validated('email') ?: $request->validated('phone')),
                    'remember' => (bool) ($request->validated('remember') ?? false),
                ],
            ]);

            $resultStatus = $result['status'] ?? 'sent';
            $status = match ($resultStatus) {
                'cooldown' => 'A login code was recently sent. Enter it below to continue.',
                'rate_limited' => 'Too many login code requests were made for this account. Please wait a while before trying again.',
                default => "We've sent a login code to " . ($result['destination'] ?? ($request->validated('email') ?: $request->validated('phone'))) . '. Enter it below to continue.',
            };

            if (in_array($resultStatus, ['sent', 'cooldown'], true)) {
                session()->flash('login_otp_code_just_sent', true);
            }

            return redirect()->route('authentication.login.verify')->with('status', $status);
        }

        $user = auth()->user();
        if ($user && empty($user->email_verified_at)) {
            // Flag so that the verify page (reached via middleware redirect or intended) will
            // immediately show the resend button as disabled + countdown (as if a resend just happened).
            session()->flash('verification_code_just_sent', true);
        }

        return redirect()->intended('/')->with('status', 'Signed in successfully.');
    }

    public function showLoginOtpVerifyForm()
    {
        $pending = session('pending_login_otp');

        if (! is_array($pending) || empty($pending['auth_method'])) {
            return redirect()->route('login')->with('error', 'Please start the login process again.');
        }

        $status = [];
        $verificationChannel = $pending['channel'] ?? ($pending['auth_method'] === 'phone_otp' ? 'phone' : 'email');
        $verificationDestination = $pending['destination'] ?? ($pending['email'] ?? $pending['phone'] ?? 'your account');
        $user = $this->registrationFollowUpService->resolveUserFromIdentifier($pending);

        if ($user) {
            $status = app(\Modules\Authentication\Services\VerificationCodeService::class)
                ->getResendStatus($user->id, $verificationChannel, 'login');
        }

        if (session()->pull('login_otp_code_just_sent')) {
            $cooldown = $status['cooldown_seconds'] ?? 60;
            $status['resend_allowed_at'] = now()->addSeconds($cooldown);
            $status['can_resend'] = false;
        }

        return view('authentication::auth.login.verify', [
            'pendingLoginOtp' => $pending,
            'resendAllowedAt' => $status['resend_allowed_at'] ?? null,
            'cooldownSeconds' => $status['cooldown_seconds'] ?? 60,
            'verificationChannel' => $verificationChannel,
            'verificationDestination' => $verificationDestination,
        ]);
    }

    public function verifyLoginOtp(OtpCodeRequest $request)
    {
        $pending = session('pending_login_otp');

        if (! is_array($pending) || empty($pending['auth_method'])) {
            return redirect()->route('login')->with('error', 'Please start the login process again.');
        }

        try {
            $result = Authentication::verifyLoginOtp($pending + $request->validated(), 'web');
        } catch (\Modules\Authentication\Exceptions\AccountLockedException $e) {
            return redirect()->route('authentication.account.locked');
        } catch (\Modules\Authentication\Exceptions\SuspendedAccountException|\Modules\Authentication\Exceptions\InactiveAccountException $e) {
            return redirect()->route('authentication.login')->with('error', $e->getMessage());
        } catch (\Modules\Authentication\Exceptions\InvalidCredentialsException $e) {
            return back()->withErrors(['code' => 'Invalid or expired code.']);
        } catch (\Modules\Authentication\Exceptions\MaxVerificationAttemptsExceededException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->forget('pending_login_otp');

        $user = $result['user'] ?? auth()->user();

        if ($user && $this->registrationFollowUpService->isPending($user)) {
            $this->registrationFollowUpService->markSessionProvisional($user);

            return $this->redirectAfterRegistrationFollowUp(
                $user,
                $this->registrationFollowUpService->nextStep($user)
            )->with('status', 'Welcome back. Finish setting up your account to continue.');
        }

        $status = $user && $this->registrationFollowUpService->passwordMissing($user)
            ? 'Signed in successfully. You have not set a password yet. Add one from your account settings.'
            : 'Signed in successfully.';

        return redirect()->intended('/')->with('status', $status);
    }

    public function resendLoginOtp()
    {
        $pending = session('pending_login_otp');

        if (! is_array($pending) || empty($pending['auth_method'])) {
            return redirect()->route('login')->with('error', 'Please start the login process again.');
        }

        try {
            $result = Authentication::resendLoginOtp($pending, 'web');
        } catch (\Throwable $e) {
            return back()->with('error', 'We could not resend the login code right now. Please try again.');
        }

        $resultStatus = $result['status'] ?? 'sent';
        $status = match ($resultStatus) {
            'cooldown' => 'Please wait a moment before requesting another login code.',
            'rate_limited' => 'Too many login code requests were made for this account. Please try again later.',
            default => "We've sent a fresh login code to " . ($pending['destination'] ?? ($pending['email'] ?: $pending['phone'])) . '.',
        };

        if (in_array($resultStatus, ['sent', 'cooldown'], true)) {
            session()->flash('login_otp_code_just_sent', true);
        }

        return back()->with('status', $status);
    }

    public function destroy(\Illuminate\Http\Request $request)
    {
        Authentication::logout(auth()->user(), 'web');

        // Full web session logout (critical for session-based auth)
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('authentication.login')->with('status', 'Signed out successfully.');
    }

    public function forgot()
    {
        return view('authentication::auth.forgot-password');
    }

    public function reset()
    {
        return view('authentication::auth.reset-password');
    }

    public function verificationNotice()
    {
        return view('authentication::auth.email-verification-notice');
    }

    public function verificationResult()
    {
        return view('authentication::auth.verify-email-result', [
            'message' => 'Your email verification request has been processed.',
        ]);
    }

    public function accountLocked()
    {
        return view('authentication::auth.account-locked');
    }

    public function verificationRequired()
    {
        $status = [];
        if (auth()->check()) {
            $status = app(\Modules\Authentication\Services\VerificationCodeService::class)
                ->getResendStatus(auth()->id(), 'email');
        }
        return view('authentication::auth.verification-required', [
            'resendAllowedAt' => $status['resend_allowed_at'] ?? null,
            'cooldownSeconds' => $status['cooldown_seconds'] ?? 60,
        ]);
    }

    public function showVerifyForm()
    {
        $status = [];
        $verificationChannel = (string) config('authentication.verification.channel', 'email');
        $verificationDestination = $verificationChannel === 'phone'
            ? (auth()->user()?->phone ?? auth()->user()?->phone_number ?? 'your phone')
            : (auth()->user()?->email ?? 'your email');
        if (auth()->check()) {
            // Provide cooldown info so the view can disable the resend button + show countdown
            $user = auth()->user();
            if ($user && $this->registrationFollowUpService->isPending($user)) {
                $verificationChannel = $this->registrationFollowUpService->verificationChannel($user);
                $verificationDestination = $this->registrationFollowUpService->verificationDestination($user);
            }
            $status = app(\Modules\Authentication\Services\VerificationCodeService::class)
                ->getResendStatus(auth()->id(), $verificationChannel, 'register');
        }

        // If a code was just sent on login/register (or resend), force the cooldown UI state
        // for the very first render of this page. This ensures the resend button is disabled
        // with countdown immediately after the "first time" send on sign-in, even if the
        // DB lastCreated timing or redirect chain makes getResendStatus return "can resend".
        if (session()->pull('verification_code_just_sent')) {
            $cooldown = $status['cooldown_seconds'] ?? 60;
            $status['resend_allowed_at'] = now()->addSeconds($cooldown);
            $status['can_resend'] = false;
        }

        return view('authentication::auth.verify', [
            'resendAllowedAt' => $status['resend_allowed_at'] ?? null,
            'cooldownSeconds' => $status['cooldown_seconds'] ?? 60,
            'verificationChannel' => $verificationChannel,
            'verificationDestination' => $verificationDestination,
        ]);
    }

    public function verifyCode(VerifyVerificationCodeRequest $request)
    {
        try {
            $userId = auth()->id();
            $result = Authentication::verifyCode($request->validated() + ['user_id' => $userId], 'web');

            if (($result['status'] ?? '') === 'verified') {
                $user = $result['user'] ?? ($userId ? auth()->user() : null);
                if ($user && !auth()->check()) {
                    auth()->login($user);
                }
                if ($user && $this->registrationFollowUpService->isPending($user)) {
                    $this->registrationFollowUpService->markSessionProvisional($user);

                    return $this->redirectAfterOtpRegistrationVerification($user)
                        ->with('status', 'Your account is verified. Finish setting up your account to continue.');
                }

                $dashboard = Route::has('dashboard')
                    ? route('dashboard')
                    : (Route::has('home') ? route('home') : url('/'));

                return redirect()->intended($dashboard)->with('status', 'Verification successful.');
            }

            return back()->withErrors(['code' => 'Invalid or expired code.']);
        } catch (\Modules\Authentication\Exceptions\MaxVerificationAttemptsExceededException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->with('status', 'Please request a new code.');
        }
    }

    public function showSetPasswordForm()
    {
        $user = auth()->user();

        if (! $user || (! $this->registrationFollowUpService->isPending($user) && ! $this->registrationFollowUpService->passwordMissing($user))) {
            return redirect()->route('login');
        }

        return view('authentication::auth.set-password', [
            'passwordRequired' => $this->registrationFollowUpService->passwordRequired(),
            'showStrengthMeter' => true,
        ]);
    }

    public function storeSetPassword(SetPasswordRequest $request)
    {
        $result = Authentication::setRegistrationPassword($request->validated(), 'web');
        $user = $result['user'] ?? auth()->user();

        return $this->redirectAfterRegistrationFollowUp($user, $result['next_step'] ?? 'dashboard')
            ->with('status', 'Password saved successfully.');
    }

    public function skipSetPassword()
    {
        if ($this->registrationFollowUpService->passwordRequired()) {
            return back()->withErrors([
                'password' => 'You need to set a password to continue.',
            ]);
        }

        $result = Authentication::skipRegistrationPassword([], 'web');
        $user = $result['user'] ?? auth()->user();

        return $this->redirectAfterRegistrationFollowUp($user, $result['next_step'] ?? 'dashboard')
            ->with('status', 'You can set a password later from your account settings.');
    }

    public function resendVerificationCode(ResendVerificationCodeRequest $request)
    {
        $channel = $request->validated('channel', (string) config('authentication.verification.channel', 'email'));
        $userId = auth()->id();

        \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND] Resend button clicked', [
            'user_id' => $userId,
            'channel' => $channel,
            'ip' => $request->ip(),
            'time' => now()->toDateTimeString(),
        ]);

        try {
            $result = Authentication::resendVerificationCode(['channel' => $channel, 'user_id' => $userId], 'web');
            $status = $result['status'] ?? 'sent';

            \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND] Resend service result', [
                'user_id' => $userId,
                'channel' => $channel,
                'status' => $status,
                'result' => $result,
            ]);

            if ($status === 'cooldown') {
                $allowed = $result['resend_allowed_at'] ?? now()->addMinute();
                $secs = max(0, (int) now()->diffInSeconds(\Carbon\Carbon::parse($allowed)));
                \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND] Cooldown active, not sending new code', [
                    'user_id' => $userId,
                    'allowed_at' => $allowed,
                    'wait_secs' => $secs,
                ]);
                return back()
                    ->with('status', "Please wait {$secs} seconds before requesting another code.")
                    ->with('resend_allowed_at', $allowed);
            }
            if ($status === 'rate_limited') {
                \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND] Rate limited (5 per hour), not sending', [
                    'user_id' => $userId,
                ]);
                return back()->with('status', 'Too many resend attempts this hour. Please try again later.');
            }

            \Illuminate\Support\Facades\Log::info('[VERIFY-RESEND] Fresh code generated and delivery attempted', [
                'user_id' => $userId,
                'channel' => $channel,
            ]);

            return back()->with('status', 'A new verification code has been generated. Please check your email inbox (and spam folder).');
        } catch (PhoneVerificationNotConfiguredException $e) {
            return back()->with('status', $e->getMessage());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[VERIFY-RESEND] Exception during resend', [
                'user_id' => $userId,
                'channel' => $channel,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('status', 'We generated a new verification code, but we are currently unable to send emails. Please try resending in a few minutes or contact support if the problem persists.');
        }
    }

    public function sendVerification()
    {
        $user = auth()->user();
        if ($user && $user->email) {
            try {
                Authentication::sendEmailVerification(['email' => $user->email], 'web');
                return back()->with('status', 'Verification email sent. Please check your inbox.');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Legacy email verification send failed', ['error' => $e->getMessage()]);
                return back()->with('status', 'We were unable to send the verification email right now due to an email server issue. Please try again later.');
            }
        }
        return back()->with('error', 'Unable to send verification.');
    }

    public function sendPhoneVerification()
    {
        // Placeholder - implement actual SMS/OTP sending via PhoneVerificationCodeSenderInterface when available
        return back()->with('status', 'Verification code sent via SMS (configure your phone verification provider).');
    }

    public function me()
    {
        return view('authentication::auth.me', [
            'user' => auth()->user(),
            'passwordMissing' => auth()->check() ? $this->registrationFollowUpService->passwordMissing(auth()->user()) : false,
        ]);
    }

    protected function viewName(string $method): string
    {
        return str_replace('_', '-', $method);
    }

    protected function redirectAfterOtpRegistrationVerification($user)
    {
        return $this->redirectAfterRegistrationFollowUp(
            $user,
            $this->registrationFollowUpService->nextStep($user)
        );
    }

    protected function redirectAfterRegistrationFollowUp($user, string $nextStep)
    {
        if ($nextStep === 'set_password') {
            $passwordSetupRoute = (string) config('authentication.after_otp_registration.password_setup_route', 'auth.set-password');

            if (Route::has($passwordSetupRoute)) {
                return redirect()->route($passwordSetupRoute);
            }
        }

        $this->registrationFollowUpService->complete($user);
        $this->registrationFollowUpService->clearSessionState();

        if ($nextStep === 'profile_completion') {
            $profileCompletionRoute = (string) config('authentication.registration.profile_completion_route', 'account.profile');

            if (Route::has($profileCompletionRoute)) {
                return redirect()->route($profileCompletionRoute);
            }
        }

        $dashboard = Route::has('dashboard')
            ? route('dashboard')
            : (Route::has('home') ? route('home') : url('/'));

        return redirect()->intended($dashboard);
    }
}
