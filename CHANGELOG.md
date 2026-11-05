# Changelog

## Unreleased

- Added the 38 remaining Season organizer operations to `$seatlayer->seasons`, which now
  has 86: performances and packages (`createSeasonPerformances`, `attachSeasonPerformances`,
  `updateSeasonPlan`, `deleteSeasonPlan`, `publishSeason`, and more), package prices,
  checkout tests, occurrence returns and reclaims, `listSeasonHolders`, season order refunds,
  cancellations and resends, hosted renewals, renewal links, contacts and notices, renewal
  quotes, intents and checkouts, the sales,
  seats and seat-count reports, holder and order CSV exports, and bulk refunds with their jobs.
- Added `$seatlayer->holders` (holder reads and updates, `listCustomers`, session sign-out,
  tickets, and ticket transfers), `$seatlayer->orders` (orders across events and seasons, and
  one event's orders with refund, cancel, resend, and CSV export), and `$seatlayer->eventSeries`
  (plan a series from a schedule, add dates, change every upcoming date).
- Added `holders->createHolderReturn`: give back a holder's seat on one date, one per ticket.
- Added event sales settings to `$seatlayer->events`: buyer checkout, payment gateway, sales
  readiness, preflight and transitions, booking fee, and discount codes.
- Replay-backed operations send an `Idempotency-Key` (yours, or a generated one reused on
  retry), as before; every other new mutation is sent once. The CSV exports return the CSV
  text as a string, and the two `204` deletes return nothing.
- Added `seasons->refundSeasonPerformance` (refund every order of a cancelled date as a job,
  with header replay), `seasons->listSeasonBulkJobs`, and `seasons->retrieveSeasonBulkJob`.
- `replaceSeasonPackagePrices` types `priceTypeKey` on each price (`adult`, `child`,
  `concession`, `senior`, `student`).
- `createSeasonAmendment` types the `postpone` and `cancel` kinds, with `refund` and `reason`.
- `updateSeason` types `checkoutMode`, `transferCutoffHours`, and `returnCutoffHours`.
- `createSeasonHolderImport` documents `holderEmail` and `holderName` on each row.
- `events->list` and `events->listAll` take `seriesKey` to list one event series' dates.
- Added `seasons->retrieveSeasonRenewalQuote`: what renewing an offer costs, and whether SeatLayer checkout can take it now.
- Added `seasons->createSeasonRenewalIntent`: record a holder's intent for a renewal taken by phone or at the box office, so your own checkout can commit.
- Added `seasons->createSeasonRenewalCheckout`: a SeatLayer checkout that renews an offer with no hosted page, with header replay.
- Added `holders->createHolderRenewalCheckout`: the same checkout for one of a holder's offers, with header replay.
- Added `holders->declineHolderRenewalOffer`: decline one of a holder's offers, with an optional reason.
- `seasons->declineSeasonRenewalOffer` takes an optional `$reason`, kept on the offer as `declineReason`.
- `updateSeason` takes `audience` (`embed_only` or `public`).
- `openSeasonSales` answers `warnings` (`checkout_test_missing` until a checkout test or rehearsal has passed) and can refuse with `409 plan_not_published` or `409 checkout_mode_not_chosen`.
- A cancelled date refunds per date only: an amendment's `refund` is `none` or `per_date`, and `refundSeasonPerformance` takes `amount` `per_date` (`full` answers `422 date_refund_per_date_only`; refund a whole order with the order refund).
- `addEventSeriesDates` takes `['retryFailed' => true]` to make the failed dates again (`409 series_busy`, `422 nothing_to_retry`).
- `listOrders` takes `$holderRef` (last argument); orders carry `holderRef` and `customerId`, and `buyer.email` can be null (a box office sale with no email).
- Season holds, give-backs and date cancels can refuse with `performance_cancelled`, `refund_not_started` (503), `missing_manage_capability` (403) or `invalid_refund`; a holder's status can be `reserved`; hosted renewal readiness carries `missingPrices`.
- `eventSeries->createEventSeries` takes `['fromEventKey' => …, 'schedule' => …]`: an event you already set up becomes the first date under its own key, and every other date is a copy of it. It refuses with `404 event_not_found`, or `422 event_has_no_date`, `event_in_season`, `event_in_performance_group`, `event_in_series` or `invalid_request`.
- Every date of an event series carries the series' plain name (`Hamlet`, not `Hamlet · 3 Nov`); tell dates apart by `startsAt`. `updateEventSeries` renames each date that carries the series name, and a series name cannot hold `{n}`, `{date}` or `{time}`.

## 0.8.0 (2026-09-10)

- Added Event hosting regions: `$region` on event create, `$defaultRegion` on workspace create and update, and the `EventHostingRegion` constants. Events created without a region run in Western Europe.

## 0.7.0 — 2026-08-30

- Added coverage for all 48 Fixed Renewable Season server
  operations under `$seatlayer->seasons`, with exact path encoding and
  operation-specific retry/idempotency behavior.
- Season allocations are identity-only and the API response declares host
  pricing authority. Buyer rehearsal validation sends no evidence body because
  SeatLayer discovers the retained hold, booking, cancellation, and delivered
  webhook chain automatically.

## 0.6.1

- Documentation only. Refreshes the README, adds frequently asked
  questions, and aligns package metadata. No API or behaviour changes.

## 0.6.0 — 2026-08-23

- Added exact immutable Event configuration binding reads and compare-and-set
  attach/detach through `Events::retrieveConfigurationBinding()` and
  `Events::updateConfigurationBinding()`. Updates remain deliberately
  single-attempt.

## 0.5.0 — 2026-08-21

- Added `performanceGroups`, the trusted server resource for fixed two-to-eight
  performance runs. It creates and activates groups, mints one-time browser
  access, retrieves authoritative group holds, and confirms bookings with
  stable action and order references. Browser-only group routes remain outside
  this secret-key SDK.

- Added `templates->instantiateTemplate()` and the ticket-release lifecycle on
  `events` (`listTicketReleases`, `updateTicketReleases`, and
  `closeTicketRelease`). Template instantiation sends `{}` when no overrides
  are supplied, URI-escapes identifiers, and uses exact-response replay;
  ticket-release writes remain single-attempt.
- **Security/reliability:** Mutations now default to a single attempt. Automatic header-replay
  retries are limited to chart create/copy, template instantiation, event create, and workspace create, preventing
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
