<?php

namespace Modules\Authentication\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Symfony\Component\HttpFoundation\Response;

class EnsureOtpRegistrationFollowUpAllowed
{
    public function __construct(
        protected RegistrationFollowUpService $registrationFollowUpService,
    ) {}

    protected array $except = [
        'authentication.verify-email',
        'authentication.verify-email.*',
        'authentication.logout',
        'auth.set-password',
        'auth.set-password.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->registrationFollowUpService->isPending($user)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        foreach ($this->except as $pattern) {
            if ($routeName && fnmatch($pattern, $routeName)) {
                return $next($request);
            }
        }

        if ($this->registrationFollowUpService->nextStep($user) === 'set_password') {
            return redirect()->route(config('authentication.after_otp_registration.password_setup_route', 'auth.set-password'))
                ->with('status', 'Finish setting up your account to continue.');
        }

        return $next($request);
    }
}
