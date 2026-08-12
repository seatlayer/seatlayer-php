# SeatLayer PHP SDK

[![CI](https://github.com/seatlayer/seatlayer-php/actions/workflows/ci.yml/badge.svg)](https://github.com/seatlayer/seatlayer-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/seatlayer/seatlayer-php.svg)](https://packagist.org/packages/seatlayer/seatlayer-php)
[![License: MIT](https://img.shields.io/badge/license-MIT-111827.svg)](LICENSE)

Official PHP server SDK for the [SeatLayer](https://seatlayer.io) reserved-seating API.

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

// 1. Provision a venue for a new organiser from one of your templates.
$chart = $seatlayer->charts->copy('c_template_arena')['meta'];
$seatlayer->charts->publish($chart['id']);

// 2. Create an event on it.
$event = $seatlayer->events->create($chart['id'], name: 'Spring Gala')['meta'];

// 3. Sell four seats over the phone.
$held = $seatlayer->inventory->holdBestAvailable($event['key'], qty: 4);
// … take payment against $held['items'], which carry authoritative prices …
$seatlayer->inventory->book($event['key'], holdId: $held['holdId'], bookingRef: 'order-8842');
```

## Test vs live

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

**Retries.** 429, 408 and 5xx are retried with exponential backoff and full jitter; `Retry-After`
wins when the server sends it. 4xx is never retried — it will not start succeeding.

**Idempotency.** Every mutating request carries an `Idempotency-Key`, generated if you do not supply
one, and **reused across retries** so a retried booking cannot become two bookings. Pass your own
order id for end-to-end deduplication:

```php
$seatlayer->inventory->book($eventKey, holdId: $holdId, idempotencyKey: "order-{$orderId}");
```

```php
new SeatLayer(
    getenv('SEATLAYER_SECRET_KEY'),
    maxRetries: 3,   // total attempts
    timeout: 30.0,   // seconds, per attempt
);
```

## Escape hatch

For surface this SDK does not wrap yet — same auth, retries, idempotency and error mapping:

```php
$seatlayer->request('POST', '/v1/events/ev_1/some-new-route', body: [...]);
```

Need your own HTTP stack? The constructor takes a `$transport` callable, which is also how the test
suite runs without a network.

## API surface

| Resource | Methods |
| --- | --- |
| `charts` | `list` `listAll` `create` `retrieve` `update` `delete` `copy` `archive` `unarchive` `publish` |
| `events` | `list` `listAll` `create` `retrieve` `update` `delete` `updateChart` `close` `reopen` `archive` `retrieveHoldTtl` `updateHoldTtl` `retrieveReport` `retrieveLog` |
| `channels` | `listChannels` `createChannel` `updateChannel` `updateAssignments` `listAllocation` `retrieveAccessPreview` `retrieveReport` `pause` `unpause` `archive` `createBuyerAccessSession` `listBuyerAccessSessions` `revokeBuyerAccessSession` |
| `inventory` | `hold` `holdBestAvailable` `bookBestAvailable` `extendHold` `retrieveHold` `release` `book` `boxOfficeBook` `unbook` `block` `unblock` `unblockAll` `retrieveAvailability` `updateAvailability` `listBookings` `retrieveBooking` |
| `sessions` | `createManageSession` `revokeManageSession` `createDesignerSession` `revokeDesignerSession` |
| `webhooks` | `list` `create` `update` `delete` `listDeliveries` |
| `workspaces` | `list` `create` `retrieve` `update` |

Full reference: [docs.seatlayer.io/server-sdk](https://docs.seatlayer.io/server-sdk/install/)

## Related resources

- [Server SDK guide](https://docs.seatlayer.io/server-sdk/install/)
- [Errors, retries and idempotency](https://docs.seatlayer.io/server-sdk/reliability/)
- [Webhook verification](https://docs.seatlayer.io/server-sdk/webhooks/)
- [Server API reference](https://docs.seatlayer.io/server-api/events/)
- [OpenAPI description](https://docs.seatlayer.io/openapi.json)
- [Agent-readable documentation](https://docs.seatlayer.io/llms.txt)
- [SeatLayer GitHub organization](https://github.com/seatlayer)

### Other SeatLayer SDKs

| Surface | Package |
|---|---|
| Browser (vanilla) | [`@seatlayer/js`](https://www.npmjs.com/package/@seatlayer/js) |
| React | [`@seatlayer/react`](https://www.npmjs.com/package/@seatlayer/react) |
| React Native | [`@seatlayer/react-native`](https://www.npmjs.com/package/@seatlayer/react-native) |
| iOS | [`seatlayer-ios`](https://github.com/seatlayer/seatlayer-ios) |
| Android | [`seatlayer-android`](https://github.com/seatlayer/seatlayer-android) |
| Flutter | [`seatlayer`](https://pub.dev/packages/seatlayer) |
| Node.js (server) | [`@seatlayer/server`](https://www.npmjs.com/package/@seatlayer/server) |
| Python (server) | [`seatlayer`](https://pypi.org/project/seatlayer/) |
| Java (server) | [`io.seatlayer:seatlayer-java`](https://central.sonatype.com/artifact/io.seatlayer/seatlayer-java) |
| Go (server) | [`github.com/seatlayer/seatlayer-go`](https://pkg.go.dev/github.com/seatlayer/seatlayer-go) |
| Ruby (server) | [`seatlayer`](https://rubygems.org/gems/seatlayer) |
| PHP (server) | [`seatlayer/seatlayer-php`](https://packagist.org/packages/seatlayer/seatlayer-php) |
| .NET (server) | [`SeatLayer`](https://www.nuget.org/packages/SeatLayer) |

## Development

```bash
composer install
vendor/bin/phpstan analyse   # level 8
vendor/bin/phpunit
```

## License

MIT
