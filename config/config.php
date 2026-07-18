<?php

return [
    'mode' => env('AUTHENTICATION_MODE', 'both'),
    'token_driver' => env('AUTHENTICATION_TOKEN_DRIVER', 'sanctum'),
    'route_prefixes' => [
        'api' => env('AUTHENTICATION_API_PREFIX', 'api/v1/auth'),
        'web' => env('AUTHENTICATION_WEB_PREFIX', 'auth'),
    ],


    'guards' => [
        'web' => 'web',
        'api' => 'sanctum',
    ],

    'middleware' => [
        'api' => ['api'],
        'web' => ['web'],
    ],

    'registration' => [
        'enabled' => env('AUTHENTICATION_REG_ENABLED', true),
        'methods' => ['email_password', 'email_otp', 'phone_otp'],
        'default_method' => env('AUTHENTICATION_REG_DEFAULT', 'email_password'),
        'show_password_strength_meter' => env('AUTHENTICATION_REG_SHOW_PASSWORD_STRENGTH_METER', true),
        'fields_per_method' => [
            'email_password' => [
                'name' => ['required' => true],
                'email' => ['required' => true],
                'password' => ['required' => true],
            ],
            'email_otp' => [
                'email' => ['required' => true],
            ],
            'phone_otp' => [
                'phone' => ['required' => true],
            ],
        ],
        // Only applies to OTP registrations (email_otp / phone_otp).
        // Redirects verified users to complete their profile before continuing.
        'post_verification_profile_completion' => true,
        'profile_completion_route' => 'account.profile',
        
        //TODO: future implementation. 
        'security_question' => [
            'enabled' => false,
            'required' => false,
        ],
    ],
    
    'after_otp_registration' => [
        'prompt_for_password' => true,
        'password_required' => false,
        'password_setup_route' => 'auth.set-password',
        // Short-lived, single-use grant for unauthenticated registration completion (API).
        'registration_grant_expires_minutes' => env('AUTHENTICATION_REGISTRATION_GRANT_EXPIRES_MINUTES', 60),
    ],

    'login' => [
        'methods' => ['email_password', 'email_otp', 'phone_otp'],
        'default_method' => env('AUTHENTICATION_LOGIN_DEFAULT', 'email_password'),
        'show_alternative_methods' => true,
        'alternative_methods' => ['email_otp'],
        'fields_per_method' => [
            'email_password' => [
                'email' => ['required' => true],
                'password' => ['required' => true],
            ],
            'email_otp' => [
                'email' => ['required' => true],
            ],
            'phone_otp' => [
                'phone' => ['required' => true],
            ],
        ],
        'remember_me' => true,
        'api_tokens' => [
            'expires_minutes' => env('AUTHENTICATION_API_TOKEN_EXPIRES_MINUTES', null),
            'remember_expires_minutes' => env('AUTHENTICATION_API_REMEMBER_TOKEN_EXPIRES_MINUTES', 43200),
        ],
    ],
    'failed_login' => [
        'max_attempts' => 5,
        'decay_seconds' => 900,
    ],

    'phone_input' => [
        'enabled' => true,
        'default_country' => env('AUTH_PHONE_DEFAULT_COUNTRY', 'US'),
        'preferred_countries' => ['IN', 'US', 'GB', 'CA'],
        'only_countries' => [],
        'library' => env('AUTH_PHONE_LIBRARY', 'intl-tel-input'),
        'cdn' => env('AUTH_PHONE_USE_CDN', true),
        'version' => env('AUTH_PHONE_VERSION', '24.0.0'),
        'separate_dial_code' => true,
        'store_format' => 'e164',
    ],

    'password_reset' => [
        'enabled' => env('AUTHENTICATION_PASSWORD_RESET_ENABLED', true),
        'methods' => ['link', 'email_otp', 'phone_otp'],
        'default_method' => env('AUTHENTICATION_RESET_DEFAULT', 'email_otp'),
        'allowed_channels' => ['email', 'phone'],
        'fields_per_method' => [
            'link' => [
                'email' => ['required' => true],
            ],
            'email_otp' => [
                'email' => ['required' => true],
            ],
            'phone_otp' => [
                'phone' => ['required' => true],
            ],
        ],
        'require_new_password_after_verification' => false,
        'auto_login_after_reset' => false,
        'security_question' => [
            'enabled' => false,
            'required' => false,
        ],
    ],
    
    /*
    | verification.enabled only controls extra verification for password-based
    | registration. OTP-based registration methods always require OTP verification.
    */
    'verification' => [
        'enabled' => false,
        'method' => 'code',
        'channel' => 'email',
    ],

    /*
    |--------------------------------------------------------------------------
    | One-Time Password (OTP)
    |--------------------------------------------------------------------------
    |
    | Global OTP configuration shared by all OTP-based features.
    |
    | These settings are used by:
    |
    | - Email verification (verification.method = code)
    | - Phone verification
    | - Login via OTP
    | - Password reset via OTP
    | - Two-factor authentication (future)
    |
    | This section controls OTP behavior only
    | (length, expiry, attempts, cooldown).
    |
    */
    'otp' => [
        'length' => 6,
        'expires_minutes' => 10,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
        'max_per_hour' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | Controls password validation requirements across the Authentication
    | module. When enabled, passwords must satisfy the configured rules
    | during registration, password reset, and future password change flows.
    |
    | The strength meter is a frontend-only helper that provides live
    | feedback to users while creating a password. Backend validation
    | remains the source of truth regardless of the meter score.
    |
    | Supported rules:
    | - Minimum password length
    | - Mixed case (uppercase + lowercase)
    | - Numbers
    | - Symbols
    | - Uncompromised password check
    |
    */
    'password_policy' => [
        'enabled' => env('AUTH_PASSWORD_POLICY_ENABLED', true),
        'min_length' => (int) env('AUTH_PASSWORD_MIN_LENGTH', 8),
        'require_mixed_case' => env('AUTH_PASSWORD_REQUIRE_MIXED_CASE', true),
        'require_numbers' => env('AUTH_PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => env('AUTH_PASSWORD_REQUIRE_SYMBOLS', true),
        'uncompromised' => env('AUTH_PASSWORD_UNCOMPROMISED', false),
        'strength_meter' => [
            'enabled' => env('AUTH_PASSWORD_STRENGTH_METER_ENABLED', true),
            'show_hints' => env('AUTH_PASSWORD_STRENGTH_METER_SHOW_HINTS', true),
            'min_score' => (int) env('AUTH_PASSWORD_STRENGTH_METER_MIN_SCORE', 3),
        ],
    ],

    'use_host_layout' => env('AUTHENTICATION_USE_HOST_LAYOUT', false),
    'host_layout' => env('AUTHENTICATION_HOST_LAYOUT', 'layouts.app'),
];
