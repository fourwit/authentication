# API

The module exposes `/api/v1/auth` endpoints for register, login, logout, password reset, **code-based verification**, and `me`.

## Request and response layer

- **Form Requests** validate and normalize input before controllers call `Authentication::…()`. Controllers do not use inline `$request->validate()`.
- **API Resources** shape successful JSON responses. User objects are exposed as `{ id, name, email }` via `AuthenticatedUserResource`; tokens via `TokenResource`. Responses are not wrapped in a top-level `data` key.
- **Errors** (401, 403, 422, 423) return plain JSON such as `{ "message": "…" }` without Resources.

See [architecture.md](architecture.md) for the full list of Form Requests, Resources, and layer rules.

## Verification Codes (Primary Flow)

```
POST /api/v1/auth/verification/send     { "channel": "email" | "phone" }
POST /api/v1/auth/verification/verify   { "channel": "...", "code": "123456" }
POST /api/v1/auth/verification/resend   { "channel": "..." }
```

- Returns 200 on send/resend with `{status, channel, destination, expires_at}` (no plain code).
- Verify returns `{status: "verified", user: {id, name, email}, next_step}` on success or 422 on failure/expired/max-attempts.
- On max attempts the body contains `code: "MAX_ATTEMPTS"`.

Protected endpoints return 403 `{code: "VERIFICATION_REQUIRED", ...}` when minimum channels are not satisfied. The `auth` middleware must be applied before `auth.verified`.
