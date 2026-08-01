# Working in this repo

This is the SeatLayer **server** SDK. It talks to the API with a secret key and must never
be usable from a browser.

## Rules

- **No runtime dependencies.** Uses ext-curl and ext-hash only — no Guzzle. Keep it that way —
  a server SDK that drags in a dependency tree is a supply-chain surface for every customer.
- **The public surface is defined upstream** by `workers/api/src/publicApi.ts` in the app repo.
  A method here must map to an operation listed there. Do not wrap internal routes.
- **Method names are the operationIds** from that manifest. Renaming one is a breaking change
  across every SeatLayer server SDK, not just this package. The one deliberate divergence is
  `$e->errorCode` (PHP's Exception already owns `$code` as an int).
- **Ergonomics live here, not in the transport.** Things like "capabilities is required" and
  "expectedUpdatedAt is required" are deliberate divergences from the raw API; each one has a
  comment saying why.

## Checks

`vendor/bin/phpstan analyse` (level 8) and `vendor/bin/phpunit`. CI runs both across PHP 8.1-8.4.
