# SeatLayer PHP Server SDK for Reserved Seating

[![CI](https://github.com/seatlayer/seatlayer-php/actions/workflows/ci.yml/badge.svg)](https://github.com/seatlayer/seatlayer-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/seatlayer/seatlayer-php.svg)](https://packagist.org/packages/seatlayer/seatlayer-php)
[![License: MIT](https://img.shields.io/badge/license-MIT-111827.svg)](LICENSE)

The official SeatLayer PHP server SDK — the trusted side of a reserved-seating
integration. Inspect what a hold really contains, price from server-owned seating-chart
data, and book with a stable `bookingRef`, while managing charts, events, inventory,
allocations, and webhooks through one typed ticketing API client.

[`seatlayer/seatlayer-php` on Packagist](https://packagist.org/packages/seatlayer/seatlayer-php) ·
[SeatLayer server SDK documentation](https://docs.seatlayer.io/server-sdk/install/) ·
[SeatLayer developer platform](https://seatlayer.io/developers/) ·
[SeatLayer JavaScript seat map SDK](https://www.npmjs.com/package/@seatlayer/js) ·
[Server API reference](https://docs.seatlayer.io/server-api/events/)

> **Server-side only.** This package authenticates with your secret key. Never expose it to a
> browser or anything a ticket buyer can reach — browser surfaces get short-lived, origin-bound
> tokens that you mint here.

## Install

```bash
composer require seatlayer/seatlayer-php
```

Requires PHP 8.1 or newer with `ext-curl`, `ext-json` and `ext-hash`. No Composer dependencies.

## Quick start

```php
use SeatLayer\SeatLayer;

$seatlayer = new SeatLayer(getenv('SEATLAYER_SECRET_KEY'));

// 1. Provision a venue for a new organiser from a public template.
$chart = $seatlayer->templates->instantiateTemplate('arena-standard')['meta'];
$seatlayer->charts->publish($chart['id']);

// 2. Create an event on it.
$event = $seatlayer->events->create($chart['id'], name: 'Spring Gala')['meta'];

// 3. Sell four seats over the phone.
$held = $seatlayer->inventory->holdBestAvailable($event['key'], qty: 4);
// … take payment against $held['items'], which carry authoritative prices …
$seatlayer->inventory->book($event['key'], holdId: $held['holdId'], bookingRef: 'order-8842');
```

## Test vs live

## Fixed Renewable Seasons

Version `0.7.0` exposes all 48 trusted organizer operations through
`$seatlayer->seasons`.

After the test hold/book/cancel journey and matching webhook deliveries,
`validateSeasonBuyerRehearsal($seasonKey)` sends no evidence body; SeatLayer
discovers the retained chain automatically. Retrieved Season holds contain
inventory identity, not an authoritative amount—your platform owns package
price, payment, order, tax, refunds, benefits, and ticket or pass delivery.

```php
$checked = $seatlayer->seasons->validateSeason([
    'sourcePerformanceGroupKeys' => ['pg_subscription_run'],
]);
$draft = $seatlayer->seasons->createSeason([
    'name' => '2027 subscription',
    'sourcePerformanceGroupKeys' => ['pg_subscription_run'],
], 'season-create-2027')['season'];
$activation = $seatlayer->seasons->activateSeason($draft['key'], $draft['revision']);
```

Treat `202` as accepted work and poll `retrieveSeasonLifecycle()` with the
returned operation identity. Buyer-session minting and domain-exact booking,
cancellation, and renewal actions remain single-attempt; only declared
header-replay catalogue mutations retry automatically.


Keys carry their own mode. `sk_test_…` keys can only touch test-mode events and `sk_live_…` only
live ones; crossing them returns `403 mode_mismatch`, surfaced as `AuthException` with
`isModeMismatch()`.

```php
$seatlayer = new SeatLayer(getenv('SEATLAYER_SECRET_KEY'));
if (getenv('APP_ENV') === 'production' && $seatlayer->mode !== 'live') {
    throw new RuntimeException('Refusing to boot production against test-mode seating data.');
}
```

## The two selling flows

**Buyer picks seats in the browser.** Your frontend holds them; your backend confirms the price and
books. Never price from what the browser sent you — `retrieveHold` is authoritative.

```php
$hold = $seatlayer->inventory->retrieveHold($eventKey, $holdId);
$total = array_sum(array_column($hold['items'], 'unitPrice'));
// … charge $total in $hold['currency'] …
$seatlayer->inventory->book($eventKey, holdId: $holdId, bookingRef: $charge->id);
```

**Your backend picks the seats.** Phone orders, box office, comps.

```php
// Payment already taken — book outright, so nothing is stranded if a second call fails.
$seatlayer->inventory->bookBestAvailable($eventKey, qty: 2, bookingRef: 'phone-1183');

// Or name the seats yourself.
$seatlayer->inventory->boxOfficeBook($eventKey, ['A-1', 'A-2'], bookingRef: 'comp-14');
```

## Private and partner sales

Channels reserve inventory for a partner, member group, presale, or other private allocation. A
buyer access session is short-lived and origin-bound, so the browser receives only the allocation
it is allowed to sell; your secret key remains on your server.

```php
$channel = $seatlayer->channels->createChannel(
    $eventKey,
    name: 'Venue members',
    accessIntent: 'private',
)['channel'];

$seatlayer->channels->updateAssignments(
    $eventKey,
    labels: ['A-1', 'A-2'],
    assignmentVersion: 1,
    targetChannelId: $channel['id'],
);

$access = $seatlayer->channels->createBuyerAccessSession(
    $eventKey,
    includePublic: false,
    allowedOrigin: 'https://members.example',
    channelIds: [$channel['id']],
    maxQuantity: 2,
);
```

Pass the returned token to the buyer SDK. For trusted backend sales, pass `channelIds` to
`hold`, `holdBestAvailable`, `book`, or `bookBestAvailable`. Setting
`ignoreChannelRestrictions: true` is an explicit privileged override and should be accompanied by
an audit `reason`.

## Listing and pagination

`list()` returns one page plus a `nextCursor`. When you want everything, `listAll()` pages for you
and yields as it goes — a `Generator` rather than an array, because the point of paginating is to
*not* hold an unbounded result set in memory.

```php
// One page, your own paging.
$page = $seatlayer->events->list(limit: 50);
$page['events'];
$page['nextCursor'] ?? null;   // absent once exhausted

// Or let the SDK walk it.
foreach ($seatlayer->events->listAll() as $event) {
    sync($event);
}
```

Listing events includes live availability `counts` by default, which costs the server one
round-trip **per event**. `listAll()` turns them off automatically — walking a whole catalogue is
exactly when you don't want that — and you can control it explicitly:

```php
$seatlayer->events->list(limit: 50, counts: false);
```

## Keeping a hold alive

When an order takes longer than the checkout window — an invoice, a phone sale — extend rather than
release and re-hold. Releasing first hands the seats to whoever is racing for them in between.

```php
use SeatLayer\ConflictException;

try {
    $seatlayer->inventory->extendHold($eventKey, $holdId, ttlMs: 10 * 60_000);
} catch (ConflictException) {
    // Gone, expired, or at its renewal cap — the buyer has to re-pick.
}
```

## Embedding the control room

Your secret key never reaches a browser. Mint a scoped token instead.

```php
$session = $seatlayer->sessions->createManageSession(
    $eventKey,
    allowedOrigin: 'https://box-office.yourplatform.com',
    capabilities: ['event:view', 'event:block'],
    expiresInSeconds: 3600,
);
```

`capabilities` is **required** by this SDK even though the API defaults it. That default grants all
four including `event:cancel`, which reverses paid bookings — not something that should arrive by
forgetting an argument. Grant the smallest set the page needs.

The same pattern embeds the Designer in your own UI:

```php
$chart = $seatlayer->charts->create('Riverside Theatre')['meta'];
$designer = $seatlayer->sessions->createDesignerSession(
    workspaceId: $workspaceId,
    chartId: $chart['id'],
    allowedOrigin: 'https://app.yourplatform.com',
    authority: 'edit',
);
```

## Webhooks

Verify every delivery against the **raw** body. Re-encoding the decoded array changes the bytes and
verification will fail.

```php
use SeatLayer\Webhook;
use SeatLayer\WebhookVerificationException;

// Laravel: $request->getContent() — never $request->all()
$payload = file_get_contents('php://input');

try {
    $event = Webhook::verify(
        $payload,
        $_SERVER['HTTP_X_SEATLAYER_SIGNATURE'] ?? null,
        getenv('SEATLAYER_WEBHOOK_SECRET'),
    );
} catch (WebhookVerificationException) {
    http_response_code(400);
    return;
}

// The signed body carries `at`, but nothing enforces a freshness window, so a
// captured delivery stays valid indefinitely. Deduplicate on occurrenceId —
// this is your replay protection, not an optimisation.
if (alreadyProcessed($event['occurrenceId'])) {
    http_response_code(200);
    return;
}

handle($event);
http_response_code(200);
```

## Errors

```php
use SeatLayer\AuthException;
use SeatLayer\ConflictException;
use SeatLayer\RateLimitException;

try {
    $seatlayer->inventory->holdBestAvailable($eventKey, qty: 6);
} catch (ConflictException $error) {
    if ($error->isSoldOut()) {
        return showAlternativeDates();      // a business outcome, not a bug
    }
    throw $error;
} catch (RateLimitException $error) {
    return retryAfter($error->retryAfterSeconds);
} catch (AuthException $error) {
    if ($error->isModeMismatch()) {
        throw new RuntimeException('Test key pointed at a live event, or the reverse.');
    }
    throw $error;
}
```

Every exception carries `status`, `errorCode`, `body`, and `requestId` — quote the request id in
support requests.

> **Naming note.** The error slug is `$e->errorCode`, not `$e->code`, because PHP's base `Exception`
> already owns `$code` as an int. Other SeatLayer SDKs expose the same value as `code`.

## Reliability

**Retries and idempotency.** Reads (`GET`/`HEAD`) retry connection failures, 408, 429 and 5xx with
exponential backoff and full jitter; `Retry-After` wins when the server sends it. Five create
operations have the same retry behaviour with header replay: `charts->create`, `charts->copy`,
`templates->instantiateTemplate`, `events->create`, and `workspaces->create`. They generate an `Idempotency-Key` when absent and reuse
that key across every attempt. You can supply a stable provisioning key instead:

```php
$seatlayer->events->create(
    $chartId,
    name: 'Spring Gala',
    idempotencyKey: "provision-event-{$eventId}",
);
```

All other mutations are single-attempt: holds, bookings, lifecycle changes, channel changes,
show-once secret creation, and raw requests. The SDK does not generate a key for them. A supplied
key on an existing method is validated and forwarded once for compatibility, but it does not
enable retries or promise replay. Reconcile bookings with their required `bookingRef`; never retry
an unknown booking outcome as though the transport had made it safe.

```php
new SeatLayer(
    getenv('SEATLAYER_SECRET_KEY'),
    maxRetries: 3,   // total attempts
    timeout: 30.0,   // seconds, per attempt
);
```

## Escape hatch

For surface this SDK does not wrap yet. Raw reads retain read retries; raw mutations use the same
auth and error mapping but are sent once and never receive an automatically generated key:

```php
$seatlayer->request('POST', '/v1/events/ev_1/some-new-route', body: [...]);
```

Need your own HTTP stack? The constructor takes a `$transport` callable, which is also how the test
suite runs without a network.

## API surface

| Resource | Methods |
| --- | --- |
| `charts` | `list` `listAll` `create` `retrieve` `update` `delete` `copy` `archive` `unarchive` `publish` |
| `templates` | `instantiateTemplate` |
| `events` | `list` `listAll` `create` `retrieve` `retrieveConfigurationBinding` `updateConfigurationBinding` `update` `delete` `updateChart` `close` `reopen` `archive` `retrieveHoldTtl` `updateHoldTtl` `listTicketReleases` `updateTicketReleases` `closeTicketRelease` `retrieveReport` `retrieveLog` |
| `channels` | `listChannels` `createChannel` `updateChannel` `updateAssignments` `listAllocation` `retrieveAccessPreview` `retrieveReport` `pause` `unpause` `archive` `createBuyerAccessSession` `listBuyerAccessSessions` `revokeBuyerAccessSession` |
| `inventory` | `hold` `holdBestAvailable` `bookBestAvailable` `extendHold` `retrieveHold` `release` `book` `boxOfficeBook` `unbook` `block` `unblock` `unblockAll` `retrieveAvailability` `updateAvailability` `listBookings` `retrieveBooking` |
| `sessions` | `createManageSession` `revokeManageSession` `createDesignerSession` `revokeDesignerSession` |
| `webhooks` | `list` `create` `update` `delete` `listDeliveries` |
| `workspaces` | `list` `create` `retrieve` `update` |

Full reference: [docs.seatlayer.io/server-sdk](https://docs.seatlayer.io/server-sdk/install/)

## Frequently asked questions

### How do I book seats from PHP?

Create a client with your secret key, obtain a hold id — either from the buyer's
browser session or by holding server-side — and call `$seatlayer->inventory->book($eventKey, holdId: ..., bookingRef: ...)`.
`bookingRef` is your own stable order id and is the join between SeatLayer
inventory and your commercial order, so the same reference identifies the booking
in Booking History and when you later cancel it. For phone orders, box office, and
comps, `$seatlayer->inventory->bookBestAvailable(...)` books outright with no browser involved.

### What does the server SDK do compared with the buyer SDK?

The buyer SDK runs where the ticket buyer is: it renders the interactive seating
chart, handles seat selection, and creates temporary holds. This server SDK is the
trusted side. It authenticates with your secret key, inspects what a hold actually
contains, prices from server-owned data, and books. Never bundle the secret key
into a browser or a mobile app — browser surfaces get short-lived, origin-bound
tokens that you mint here.

### How do temporary holds work server-side?

A hold reserves seats against concurrent buyers for a limited window.
`$seatlayer->inventory->retrieveHold($eventKey, $holdId)` is the authoritative answer for what is held
and at what price, so charge from its `items` rather than from anything the browser
sent you. When an order runs longer than the checkout window, `$seatlayer->inventory->extendHold(...)`
renews the hold instead of releasing and re-holding, which would hand the seats to
whoever is racing for them. Bookings carry the server's exact-selection plus
`bookingRef` safeguard, but the SDK sends each booking once — reconcile an unknown
outcome before trying again.

### Can I use my own payment provider?

Yes. SeatLayer never processes payment. Inspect the hold, compute the charge from
the returned `items` and their authoritative `unitPrice` and `currency`, take the
money through whichever provider you already use — Stripe, Adyen, Razorpay, or your
own — and then book the hold with your order id as `bookingRef`. SeatLayer owns
seating state, holds, booking concurrency, and the inventory ledger; your platform
owns payments, commercial orders, tickets, delivery, and refunds.

## Continue your PHP integration

- [Follow the SeatLayer server SDK guide](https://docs.seatlayer.io/server-sdk/install/)
  for installation, authentication, and the full hold-to-booking flow.
- [Handle errors, retries, and safe booking repeats](https://docs.seatlayer.io/server-sdk/reliability/)
  before connecting a production order flow.
- [Verify SeatLayer webhooks](https://docs.seatlayer.io/server-sdk/webhooks/)
  to react to holds, expiry, and bookings on your server.
- [Browse the SeatLayer server API reference](https://docs.seatlayer.io/server-api/events/)
  for every endpoint behind this SDK.
- [Generate clients from the SeatLayer OpenAPI description](https://docs.seatlayer.io/openapi.json)
  or explore the raw API surface.
- [Point AI coding agents at the SeatLayer docs index](https://docs.seatlayer.io/llms.txt)
  (`llms.txt`) for an agent-readable map of the documentation.
- [Explore every SeatLayer SDK on GitHub](https://github.com/seatlayer)
  across web, mobile, and server.

### Other SeatLayer SDKs

| Surface | Package or source |
| --- | --- |
| JavaScript | [`@seatlayer/js`](https://www.npmjs.com/package/@seatlayer/js) |
| React | [`@seatlayer/react`](https://www.npmjs.com/package/@seatlayer/react) |
| React Native | [`@seatlayer/react-native`](https://www.npmjs.com/package/@seatlayer/react-native) |
| iOS | [`seatlayer-ios`](https://github.com/seatlayer/seatlayer-ios) |
| Flutter | [`seatlayer`](https://pub.dev/packages/seatlayer) |
| Android | [`seatlayer-android`](https://github.com/seatlayer/seatlayer-android) |
| Node.js (server) | [`@seatlayer/server`](https://www.npmjs.com/package/@seatlayer/server) |
| Python (server) | [`seatlayer`](https://pypi.org/project/seatlayer/) |
| PHP (server) | [`seatlayer/seatlayer-php`](https://packagist.org/packages/seatlayer/seatlayer-php) (this package) |
| Ruby (server) | [`seatlayer`](https://rubygems.org/gems/seatlayer) |
| .NET (server) | [`SeatLayer`](https://www.nuget.org/packages/SeatLayer) |
| Java (server) | [`io.seatlayer:seatlayer-java`](https://central.sonatype.com/artifact/io.seatlayer/seatlayer-java) |
| Go (server) | [`github.com/seatlayer/seatlayer-go`](https://pkg.go.dev/github.com/seatlayer/seatlayer-go) |

## Development

```bash
composer install
vendor/bin/phpstan analyse   # level 8
vendor/bin/phpunit
```

## License

MIT
