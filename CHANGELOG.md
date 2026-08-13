# Changelog

## Unreleased

- **Security/reliability:** Mutations now default to a single attempt. Automatic header-replay
  retries are limited to chart create/copy, event create, and workspace create, preventing
  transient failures from duplicating holds or best-available results and from issuing extra
  show-once credentials.

## 0.2.0 — 2026-08-12

- Added channel allocation management and origin-bound buyer access sessions.
- Added channel-aware hold and booking controls, including explicit privileged override reasons.
- Added paginated booking lifecycle reads and encoded booking retrieval.
- Booking and cancellation calls now reject missing or blank stable booking references.
- Expanded the README with private-sale guidance and direct links across the SeatLayer SDK family.

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
