# Changelog

## 0.1.0 — unreleased

First release of the SeatLayer PHP server SDK.

- `SeatLayer` client with secret-key auth, per-attempt timeouts, and a typed escape hatch.
- Resources: `charts`, `events`, `inventory`, `sessions`, `webhooks`, `workspaces`.
- Automatic `Idempotency-Key` on every mutation, reused across retries so a retried
  booking cannot become two bookings.
- Retries on 429/408/5xx with exponential backoff and full jitter; honours `Retry-After`.
  4xx is never retried.
- Typed exceptions: `AuthException` (with `isModeMismatch()`), `ConflictException`
  (with `conflicts()` and `isSoldOut()`), `RateLimitException`, `ValidationException`,
  `NotFoundException`, `ConnectionException`.
- `Webhook::verify()` — raw-body HMAC-SHA256 verification via `hash_equals`.
- `createManageSession` requires explicit capabilities; the API's default grants
  `event:cancel`, which reverses paid bookings.
- Constructor rejects a `pk_` key by name rather than failing as a 401 later.
- `listAll()` helpers page transparently as Generators.
- No runtime dependencies beyond ext-curl, ext-json and ext-hash.

### Naming note

The error slug is exposed as `$e->errorCode`, not `$e->code`, because PHP's base
`Exception` already owns `$code` as an int and a property cannot be redeclared
readonly. Other SeatLayer SDKs expose the same value as `code`.
