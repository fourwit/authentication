<?php

namespace Modules\Authentication\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Authentication\Facades\Authentication;
use Modules\Authentication\Http\Requests\RegisterRequest;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Authentication\Support\RegistrationMethodResolver;
use Modules\Authentication\Support\VerificationConfig;

class RegisterController extends Controller
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

        $method = RegistrationMethodResolver::resolve(
            old('auth_method', $request->query('auth_method'))
        );
        $fields = RegistrationMethodResolver::fields($method);

        return view("authentication::auth.register.{$this->viewName($method)}", [
            'authMethod' => $method,
            'fieldDefinitions' => $fields,
            'requiredFields' => RegistrationMethodResolver::requiredFields($method),
            'optionalFields' => RegistrationMethodResolver::optionalFields($method),
        ]);
    }

    public function store(RegisterRequest $request)
    {
        $result = Authentication::register($request->validated(), 'web');
        $user = $result['user'] ?? null;
        $authMethod = $request->validated('auth_method');

        if ($user) {
            auth()->login($user);
            if ($this->registrationFollowUpService->isOtpRegistrationMethod($authMethod)) {
                $this->registrationFollowUpService->markSessionProvisional($user);
            }
        }

        if (! VerificationConfig::registrationRequiresVerification($authMethod)) {
            $target = Route::has('dashboard')
                ? route('dashboard')
                : (Route::has('home') ? route('home') : url('/'));

            return redirect()->intended($target)->with(
                'status',
                'Your account has been created successfully.'
            );
        }

        if ($user) {
            session()->flash('verification_code_just_sent', true);
        }

        $verificationTarget = $user?->email ?: 'your email address';
        $statusMessage = ($result['reused_unverified'] ?? false)
            ? "Welcome back! We've sent a fresh verification code to {$verificationTarget}. Enter it below to activate your account."
            : "Welcome! We've just sent a verification code to {$verificationTarget}. Enter it below to activate your account.";

        return redirect()->route('authentication.verify-email')->with(
            'status',
            $statusMessage
        );
    }

    protected function viewName(string $method): string
    {
        return str_replace('_', '-', $method);
    }
}
