# Web

The module exposes `/auth` web routes for login, logout, registration, password reset, and **code-based verification** flows.

## Verification Code Flow (Primary)

- `GET /auth/verify-email` – shows the enter-code screen.
- `POST /auth/verify-email` – submits the 6-digit code for the active channel.
- `POST /auth/verify-email/resend` – requests a fresh code (invalidates prior active code for the channel; respects cooldown).

After successful registration (when `registration.verification.email/phone` are enabled), the user is redirected to the verify screen. The `auth.verified` middleware protects routes and redirects unverified users to `authentication.verification.required` (which links to the enter-code page and resend forms).

Codes:
- Are generated with configurable length.
- Expire after `verification.code_expires_minutes`.
- Are limited by `verification.max_attempts`.
- Are stored only as hashes.
- Update `email_verified_at` / `phone_verified_at` on the Identity user record on success.

Resend and error states (expired, wrong code, max attempts) are handled with clear messages and the resend button.
