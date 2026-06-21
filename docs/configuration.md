# Configuration

Set `AUTHENTICATION_MODE`, `AUTHENTICATION_USER_MODE`, `AUTHENTICATION_TOKEN_DRIVER`, route prefixes, and failed login settings in your host environment.

## Verification (Code-based primary; supports link)

Use the full structure. Cooldowns/attempts apply to code flow. Unverified users are redirected to /auth/verify-email (not dashboard).

```php
'verification' => [
    'enabled' => env('AUTHENTICATION_VERIFICATION_ENABLED', true),
    'mode' => env('AUTHENTICATION_VERIFICATION_MODE', 'code'), // code|link|both
    'channels' => ['email', 'phone'],
    'logic' => env('AUTHENTICATION_VERIFICATION_LOGIC', 'any'), // any|all
    'minimum_verified_channels' => (int) env('AUTHENTICATION_MINIMUM_VERIFIED_CHANNELS', 1),
    'code' => [
        'length' => (int) env('AUTHENTICATION_VERIFICATION_CODE_LENGTH', 6),
        'expires_minutes' => (int) env('AUTHENTICATION_VERIFICATION_CODE_EXPIRES_MINUTES', 10),
        'max_attempts' => (int) env('AUTHENTICATION_VERIFICATION_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('AUTHENTICATION_VERIFICATION_RESEND_COOLDOWN_SECONDS', 60),
    ],
    'link' => [
        'signed_routes' => env('AUTHENTICATION_VERIFICATION_SIGNED_ROUTES', true),
        'expires_minutes' => (int) env('AUTHENTICATION_VERIFICATION_LINK_EXPIRES_MINUTES', 60),
    ],
],
```

Rules:
- Only hashed codes are stored (`code_hash`).
- Plain codes are never logged.
- Email uses Laravel Notifications (publishable template).
- Phone requires a bound `PhoneVerificationCodeSenderInterface` implementation; otherwise a clear `PhoneVerificationNotConfiguredException` is thrown when a phone channel code is requested.
- Successful verification updates the user's `email_verified_at` or `phone_verified_at` via Identity.
- The `auth.verified` middleware enforces `minimum_verified_channels` using `any`/`all` logic on protected routes (exempts login/register/password/reset/verify flows).

## Login / Registration / Password Reset

See the full `config/config.php` in the package for dynamic `registration.methods`, ordered `registration.fields_per_method`, login options, and password reset options. All validation happens in the request classes using shared support helpers and resolvers.

## Password Policy

Use the `password_policy` block to centralize password rules for registration and password reset:

```php
'password_policy' => [
    'enabled' => env('AUTH_PASSWORD_POLICY_ENABLED', true),
    'min_length' => (int) env('AUTH_PASSWORD_MIN_LENGTH', 8),
    'require_mixed_case' => env('AUTH_PASSWORD_REQUIRE_MIXED_CASE', true),
    'require_numbers' => env('AUTH_PASSWORD_REQUIRE_NUMBERS', true),
    'require_symbols' => env('AUTH_PASSWORD_REQUIRE_SYMBOLS', true),
    'uncompromised' => env('AUTH_PASSWORD_UNCOMPROMISED', true),
    'strength_meter' => [
        'enabled' => env('AUTH_PASSWORD_STRENGTH_METER_ENABLED', true),
        'show_hints' => env('AUTH_PASSWORD_STRENGTH_METER_SHOW_HINTS', true),
        'min_score' => (int) env('AUTH_PASSWORD_STRENGTH_METER_MIN_SCORE', 3),
    ],
],
```

Rules:
- Backend validation is the source of truth and is shared across registration and reset-password flows.
- The module uses Laravel's native `Illuminate\Validation\Rules\Password` builder when `enabled` is `true`.
- When `enabled` is `false`, the module falls back to minimal password validation (`required`, `string`, `confirmed`, `min:8`).
- `uncompromised=true` enables Laravel's compromised-password check. Depending on host infrastructure, that may perform outbound checks at validation time.
- The frontend strength meter is advisory UI only. It does not replace backend enforcement.

## Registration Password Meter

Use `registration.show_password_strength_meter` to control whether registration screens render the packaged password meter:

```php
'registration' => [
    'show_password_strength_meter' => env('AUTHENTICATION_REG_SHOW_PASSWORD_STRENGTH_METER', true),
],
```

Rules:
- The meter only renders on registration methods that actually include a `password` field.
- The meter only renders when both `registration.show_password_strength_meter` and `password_policy.strength_meter.enabled` are `true`.
- `password_policy.strength_meter.show_hints` controls whether the checklist hints render below the meter.

## Phone Input

Use the `phone_input` block to control all phone-entry behavior in Authentication:

```php
'phone_input' => [
    'enabled' => true,
    'default_country' => env('AUTH_PHONE_DEFAULT_COUNTRY', 'IN'),
    'preferred_countries' => ['IN', 'US', 'GB', 'CA'],
    'only_countries' => [],
    'library' => env('AUTH_PHONE_LIBRARY', 'intl-tel-input'),
    'cdn' => env('AUTH_PHONE_USE_CDN', true),
    'version' => env('AUTH_PHONE_VERSION', '24.0.0'),
    'separate_dial_code' => true,
    'store_format' => 'e164', // e164|international|national
],
```

Rules:
- `store_format` must be one of `e164`, `international`, or `national`.
- Phone values are normalized before Authentication calls `Identity::createUser()` or other phone-aware lookup/update paths.
- When `enabled` is `false`, phone fields still render as plain inputs without the enhanced country picker behavior.
- `default_country` drives normalization when the submitted phone number does not include a country code.
- `library` controls the front-end phone widget:
  - `intl-tel-input` enables the packaged Blade component integration.
  - `none` and `custom` render a plain `<input type="tel">` fallback.
- When `cdn` is `true`, the packaged phone component loads `intl-tel-input` assets from jsDelivr using `version`.
- When `cdn` is `false`, the host app must provide the library assets.
- `separate_dial_code`, `preferred_countries`, and `only_countries` are forwarded to the packaged phone input component.

## Replacing the Phone UI

The module renders phone fields through `authentication::components.phone-input`.

Hosts can override it by publishing the component:

```bash
php artisan vendor:publish --tag=authentication-components
```

Published path:

```text
resources/views/vendor/authentication/components/phone-input.blade.php
```

That lets the host replace the default implementation with Alpine, Livewire, React, or a fully custom phone UI while keeping the same backend normalization and validation rules.

## Notifier

```php
'notifier' => env('AUTHENTICATION_NOTIFIER', \Modules\Authentication\Notifiers\LaravelAuthenticationNotifier::class),
```

Swap this to integrate a future `fourwit/notifications` module.

## Layout / Theming (Reusable Drop-in)

- `AUTHENTICATION_USE_HOST_LAYOUT=true`
- `AUTHENTICATION_HOST_LAYOUT=layouts.app`

Allows the auth pages to render inside your host layout (header/footer) while keeping the module self-contained for scratch projects (Tailwind CDN by default).
