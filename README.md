# Authentication

Fourwit Authentication provides login, logout, registration, password reset, and **verification code** flows (email + phone) for shared or owned user modes.

**Verification uses short-lived hashed codes (never plain text in DB or logs).** Links are not used as the primary flow.

## Quick Config (Verification Codes)

```php
'verification' => [
    'mode' => 'code',
    'code_length' => 6,
    'code_expires_minutes' => 10,
    'max_attempts' => 5,
    'channels' => ['email', 'phone'],
    'minimum_verified_channels' => 1,
    'logic' => 'any',
],
```

On registration, codes are sent for enabled channels. Users visit `GET /auth/verify` (or call the API) to enter the code. Resend supported. Codes expire and have attempt caps. Successful verify sets `email_verified_at` / `phone_verified_at` on the Identity user.

Protected routes use the `auth.verified` middleware (post-login enforcement only).

## Routes

**Web (code verification):**
- `GET /auth/verify-email`
- `POST /auth/verify-email`
- `POST /auth/verify-email/resend`

**API:**
- `POST /api/v1/auth/verification/send`
- `POST /api/v1/auth/verification/verify`
- `POST /api/v1/auth/verification/resend`

Legacy email verification routes remain for backward compat but code mode is default.

## Identity integration

User creation and lookup go through `Modules\Identity\Facades\Identity`.

## Docs

- `docs/installation.md`
- `docs/configuration.md`
- `docs/api.md`
- `docs/web.md`
- `docs/identity-integration.md`
- `docs/testing.md`
