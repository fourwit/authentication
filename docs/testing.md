# Testing

Run `composer dump-autoload`, `php artisan optimize:clear`, `php artisan route:list`, and `php artisan test` in the host app after syncing the module.

## Verification Code Tests (run in host Modules/Authentication)

The suite covers:
- Code generation (length, numeric, hashed storage only)
- Email send on registration + explicit send (Notification fake)
- Successful verify (updates email_verified_at / phone_verified_at, grants protected access)
- Expired code rejection
- Wrong code rejection + attempt counter
- Max attempts rejection (custom exception + 422 with message)
- Resend invalidates previous active code
- Protected route access after minimum verified channels (middleware)
- Phone channel blocked with clear PhoneVerificationNotConfiguredException when no sender bound (test binds a fake sender to prove the positive path)

Use `RefreshDatabase`. Bind `PhoneVerificationCodeSenderInterface` in tests when exercising phone paths. Identity users are created via `Identity::createUser(...)` (supply first_name/last_name).
