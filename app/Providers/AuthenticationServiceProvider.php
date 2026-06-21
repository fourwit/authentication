<?php

namespace Modules\Authentication\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Authentication\AuthenticationManager;
use Modules\Authentication\Facades\Authentication as AuthenticationFacade;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AuthenticationServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Authentication';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'authentication';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton('authentication', AuthenticationManager::class);
        $this->mergeConfigFrom(module_path($this->name, 'config/config.php'), 'authentication');

        // Default bindings for verification code flow (email via Laravel Notification)
        $this->app->bind(
            \Modules\Authentication\Contracts\VerificationNotifierInterface::class,
            \Modules\Authentication\Notifiers\EmailVerificationCodeNotifier::class
        );

        // Register verification middleware alias EARLY (in register phase) so it is available
        // when other modules (e.g. Identity) build their route groups using 'auth.verified'.
        $this->app['router']->aliasMiddleware('auth.verified', \Modules\Authentication\Http\Middleware\EnsureMinimumVerificationSatisfied::class);
        $this->app['router']->aliasMiddleware('auth.registration.complete', \Modules\Authentication\Http\Middleware\EnsureOtpRegistrationFollowUpAllowed::class);

        // Phone sender must be explicitly bound by the application if phone channel is used.
        // If not bound and phone verification is requested, VerificationCodeService throws
        // PhoneVerificationNotConfiguredException (clear config error).
    }

    public function boot(): void
    {
        parent::boot();

        \Modules\Authentication\Support\PhoneInputConfig::storeFormat();

        AuthenticationFacade::clearResolvedInstance('authentication');

        // Also ensure alias at boot (idempotent / defensive)
        $this->app['router']->aliasMiddleware('auth.verified', \Modules\Authentication\Http\Middleware\EnsureMinimumVerificationSatisfied::class);
        $this->app['router']->aliasMiddleware('auth.registration.complete', \Modules\Authentication\Http\Middleware\EnsureOtpRegistrationFollowUpAllowed::class);

        // Make Laravel's mail components available (for legacy @component('mail::...') usage
        // and for any published overrides that may reference them). We register both
        // published locations and the framework html/ dir (where message.blade.php, button.blade.php live).
        // This prevents "No hint path defined for [mail]" for custom module mail views.
        $mailViewPaths = [];
        // Host-published laravel-mail components (preferred when present)
        if (is_dir($pubHtml = resource_path('views/vendor/mail/html'))) {
            $mailViewPaths[] = $pubHtml;
        } elseif (is_dir($pubMail = resource_path('views/vendor/mail'))) {
            $mailViewPaths[] = $pubMail;
            if (is_dir($pubMail . '/html')) {
                $mailViewPaths[] = $pubMail . '/html';
            }
        }
        // Framework-bundled HTML mail components (button, message, layout, etc.)
        $fwHtml = base_path('vendor/laravel/framework/src/Illuminate/Mail/resources/views/html');
        if (is_dir($fwHtml)) {
            $mailViewPaths[] = $fwHtml;
        }
        // Also include the raw framework mail root as a fallback for some setups
        $fwRoot = base_path('vendor/laravel/framework/src/Illuminate/Mail/resources/views');
        if (is_dir($fwRoot)) {
            $mailViewPaths[] = $fwRoot;
        }
        if (! empty($mailViewPaths)) {
            $this->loadViewsFrom($mailViewPaths, 'mail');
        }

        Blade::component('authentication::components.password-reminder-banner', 'authentication-password-reminder');

        View::composer('*', function ($view): void {
            $user = auth()->user();
            $followUpService = app(RegistrationFollowUpService::class);
            $passwordMissing = $user ? $followUpService->passwordMissing($user) : false;
            $passwordRoute = (string) config('authentication.after_otp_registration.password_setup_route', 'auth.set-password');

            $view->with('authenticationPasswordReminder', [
                'show' => $passwordMissing,
                'message' => 'Your account does not have a password yet. Set one now so you can also sign in with email and password.',
                'action_label' => $passwordMissing ? 'Set password' : 'Change password',
                'action_route' => Route::has($passwordRoute) ? route($passwordRoute) : null,
            ]);
        });

        // Publish welcome email template so hosts can customize branding
        $this->publishes([
            module_path($this->name, 'resources/views/emails') => resource_path('views/vendor/authentication/emails'),
        ], 'authentication-emails');

        $this->publishes([
            module_path($this->name, 'resources/views/components') => resource_path('views/vendor/authentication/components'),
        ], 'authentication-components');
    }
}
