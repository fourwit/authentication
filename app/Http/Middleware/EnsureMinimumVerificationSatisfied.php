<?php

namespace Modules\Authentication\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Authentication\Support\VerificationConfig;
use Symfony\Component\HttpFoundation\Response;

class EnsureMinimumVerificationSatisfied
{
    /**
     * Routes that should never be blocked by verification check.
     */
    protected array $except = [
        'authentication.login',
        'authentication.logout',
        'authentication.register',
        'authentication.password.*',
        'authentication.verification.*',
        'authentication.verify-email',
        'authentication.verify-email.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! VerificationConfig::enabled()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Skip for exempt routes
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $channels = [VerificationConfig::channel()];
        $min = 1;
        $logic = 'any';

        $verifiedChannels = $this->getVerifiedChannels($user);

        $satisfied = $this->isSatisfied($verifiedChannels, $channels, $min, $logic);

        if ($satisfied) {
            return $next($request);
        }

        // Not satisfied - handle response
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Verification required.',
                'code' => 'VERIFICATION_REQUIRED',
                'required_channels' => $channels,
                'verified_channels' => $verifiedChannels,
                'minimum' => $min,
                'logic' => $logic,
            ], 403);
        }

        // Web: redirect unverified users to the code entry page (per requested flow)
        return redirect()->route('authentication.verify-email')
            ->with('warning', 'Please verify your account to continue.');
    }

    protected function shouldSkip(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return false;
        }

        foreach ($this->except as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    protected function getVerifiedChannels($user): array
    {
        $verified = [];

        // Email
        if (!empty($user->email_verified_at) || (method_exists($user, 'hasVerifiedEmail') && $user->hasVerifiedEmail())) {
            $verified[] = 'email';
        }

        // Phone - check user or identity profile
        $phoneVerified = $user->phone_verified_at ?? null;
        if (empty($phoneVerified) && isset($user->identityProfile)) {
            $phoneVerified = $user->identityProfile->phone_verified_at ?? null;
        }
        if (!empty($phoneVerified)) {
            $verified[] = 'phone';
        }

        return array_unique($verified);
    }

    protected function isSatisfied(array $verified, array $requiredChannels, int $min, string $logic): bool
    {
        $verifiedInRequired = array_intersect($verified, $requiredChannels);
        $count = count($verifiedInRequired);

        if ($logic === 'all') {
            return $count === count($requiredChannels);
        }

        // 'any' logic
        return $count >= $min;
    }
}
