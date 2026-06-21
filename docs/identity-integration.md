# Identity Integration

Authentication delegates user creation and lookup to `Modules\Identity\Facades\Identity` and does not duplicate profile CRUD.

## Verification Fields

On successful code verification the module updates (via `Identity::updateUser`):

- `email_verified_at` (for email channel)
- `phone_verified_at` (for phone channel)

The `EnsureMinimumVerificationSatisfied` middleware (`auth.verified`) inspects these fields (plus legacy `hasVerifiedEmail()`) plus `identityProfile.*_verified_at` when present to decide if the minimum channel requirement is satisfied.

Identity owns the schema and storage of these timestamp columns.
