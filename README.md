# SeatLayer PHP SDK: seat booking, reserved seating and season ticket API

[![CI](https://github.com/seatlayer/seatlayer-php/actions/workflows/ci.yml/badge.svg)](https://github.com/seatlayer/seatlayer-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/seatlayer/seatlayer-php.svg)](https://packagist.org/packages/seatlayer/seatlayer-php)
[![License: MIT](https://img.shields.io/badge/license-MIT-111827.svg)](LICENSE)

`composer require seatlayer/seatlayer-php` gives a Laravel, Symfony or plain PHP 8.1 app SeatLayer's
official API client for reserved seating. Read a buyer's held seats with their real prices, book
them under your order id, sell at the box office and run season tickets and renewals, without adding
a single Composer dependency.

## What SeatLayer is

SeatLayer is reserved-seat ticketing and seating software: organizers sell with hosted checkout,
a box office, door check-in and season tickets, and platforms add the same seat maps, with 3D
seat views, through the SeatLayer SDK and API. One chart drives the 2D map, the 3D venue and the
view from each seat (3D for venues up to 60,000 seats; the 2D map benchmarked at 200,000 seats).

[Website](https://seatlayer.io/) · [For developers](https://seatlayer.io/developers/) ·
[Season tickets](https://seatlayer.io/season-tickets/) · [Pricing](https://seatlayer.io/pricing/) ·
[Live demo](https://seatlayer.io/demo/)

[`seatlayer/seatlayer-php` on Packagist](https://packagist.org/packages/seatlayer/seatlayer-php) ·
[PHP server SDK guide](https://docs.seatlayer.io/server-sdk/php/) ·
[SeatLayer developer platform](https://seatlayer.io/developers/) ·
[SeatLayer JavaScript seat map SDK](https://www.npmjs.com/package/@seatlayer/js) ·
[Server API reference](https://docs.seatlayer.io/server-api/events/)

> **Server-side only.** This package authenticates with your secret key. Never expose it to a
> browser or anything a ticket buyer can reach. Browser surfaces get short-lived, origin-bound
> tokens that you mint here.

**How it works:** the buyer picks and holds seats in your website or app with your public key. Your server then reads the hold, takes the payment through your own provider, and books the seats with this SDK and your secret key.

**Start here:** [Quickstart](https://docs.seatlayer.io/start/quickstart/) · [Holds and checkout](https://docs.seatlayer.io/buyer-sdk/holds-and-checkout/) · [PHP server SDK guide](https://docs.seatlayer.io/server-sdk/php/) · [SDK catalog](https://docs.seatlayer.io/sdk-catalog.json) · [Pricing](https://seatlayer.io/pricing/): 100 sold seats free every month, then 10¢ down to 5¢ per sold seat. No subscription, and purchased credits never expire. Test mode is free.

## Install the PHP seat booking SDK

```bash
composer require seatlayer/seatlayer-php
```

Requires PHP 8.1 or newer with `ext-curl`, `ext-json` and `ext-hash`. No Composer dependencies.

## Quick start

```php
use SeatLayer\SeatLayer;

$seatlayer = new SeatLayer(getenv('SEATLAYER_SECRET_KEY'));

// 1. Provision a venue for a new organiser from a public template.
// Replace this placeholder with a template id from your catalog.
$chart = $seatlayer->templates->instantiateTemplate('your-published-template')['meta'];
$seatlayer->charts->publish($chart['id']);

// 2. Create an event on it.
$event = $seatlayer->events->create(
    $chart['id'], name: 'Spring Gala',
    currency: 'EUR', // omit to inherit the workspace currency
    region: \SeatLayer\EventHostingRegion::WESTERN_EUROPE // India: ASIA_PACIFIC
)['meta'];

// 3. Sell four seats over the phone.
$held = $seatlayer->inventory->holdBestAvailable($event['key'], qty: 4);
// … take payment against $held['items'], which carry authoritative prices …
$seatlayer->inventory->book($event['key'], holdId: $held['holdId'], bookingRef: 'order-8842');
```

## Event hosting region

Pass the named `region` argument to `events->create()` based on the **event venue**, not your API
server or office. It controls the initial placement of the Event's live inventory;
an existing Event cannot be moved later. Omit it to inherit the workspace default (`western-europe` for new accounts).
Pass `defaultRegion` to `workspaces->create()` or in the fields sent to
`workspaces->update()`; changing it affects only future Events.

- `western-europe`, `eastern-europe`, `north-america-east`, `north-america-west`, `south-america`
- `asia-pacific`, `northeast-asia`, `southeast-asia`, `oceania`, `africa`, `middle-east`

The hint is best effort, not a data-residency guarantee. See the
[full Event region guide](https://docs.seatlayer.io/server-api/event-regions/).

## Season tickets (Fixed Renewable Seasons)

`$seatlayer->seasons` has all 90 season operations: seasons and their packages, dates and
package prices (per price type, set with `priceTypeKey`: `adult`, `child`, `concession`,
`senior` or `student`), sales windows, checkout tests, buyer sessions and booking, holder
imports and the holder list, season orders, renewals (offers, quotes, intents, checkouts and
hosted renewal links), date changes and cancellations, season seats, bulk refunds with their
jobs, sales and seat reports, CSV exports, audit and support export. The bulk refund of a
cancelled date is `refundSeasonPerformance`, the release of every lapsed renewal offer's seats
is `releaseSeasonLapsedOffers`, and their jobs are read with `listSeasonBulkJobs` and
`retrieveSeasonBulkJob`.

Before you open sales, run one test hold, booking and cancellation with their
webhooks, then call `validateSeasonBuyerRehearsal($seasonKey)`. It finds those
test records itself, so it takes no body. With your own checkout, until a test like this or a checkout test has passed, opening sales answers with a `checkout_test_missing` warning. A
season hold lists each date's seat, category and quantity but no price: when you
run your own checkout, you set the package price and take the payment, and you
issue the tickets.

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

A `202` answer means the work was accepted and is still running: keep the returned
operation id and poll `retrieveSeasonLifecycle()` until it finishes. Bookings,
cancellations and renewal commits are sent once and never retried by the SDK, so
give each one your own stable id; sending the same id again is safe. A buyer session is sent once too, and repeating it is not safe: if its answer is lost, revoke
what was created and create one new session.

Above 100 contracts, `createSeasonRenewalOffers()` answers `202` with a `renewal_run` `job`
and its `batchId` instead of `offers`; above 100 holders, `createSeasonHolderImportFromSeason()`
answers `202` with a `holder_import` `job`. Follow either with `retrieveSeasonBulkJob()`.
`listSeasonRenewalOffers()` and `retrieveSeasonHostedRenewals()` read one page at a time: pass
the answer's `nextCursor` back as `$cursor` until it is null.

`exportSeasonHoldersCsv()` and `exportSeasonOrdersCsv()` return the CSV text as a string,
not decoded JSON. `deleteSeasonPlan()` returns nothing (the API answers `204`).

When SeatLayer takes the season's payments, renew a holder with no hosted page: read the price,
start a checkout, and send the holder to `payment.url`. For a renewal taken by phone or at the
box office, `createSeasonRenewalIntent()` records the holder's intent before you commit the
offer. A decline can carry the holder's reason.

```php
$quote = $seatlayer->seasons->retrieveSeasonRenewalQuote($seasonKey, $offerId);
if ($quote['problem'] === null) {
    $started = $seatlayer->seasons->createSeasonRenewalCheckout(
        $seasonKey,
        $offerId,
        ['returnUrl' => 'https://tickets.yourclub.example/renewed'],
        "renew-{$offerId}",
    );
    header('Location: ' . $started['payment']['url']);
}

$seatlayer->holders->declineHolderRenewalOffer('holder_123', $offerId, 'Moving away');
```

### Season seats

Season seats are the seats a season sets aside on every date before its sales open. A season
buyer's hold then doesn't have to ask each date, and single-ticket buyers see those seats as
unavailable. They are off for every season until you send `'inventoryMode' => 'block'` to
`createSeason()` or `updateSeason()`. The season needs one package, and its sales must not have
opened.

```php
$seatlayer->seasons->updateSeason($seasonKey, ['expectedRevision' => $revision, 'inventoryMode' => 'block']);

$job = $seatlayer->seasons->setAsideSeasonBlock($seasonKey, [
    'expectedRevision' => $revision,
    'keepForSingleTickets' => ['sectionIds' => ['sec_family']],
])['job'];
while (in_array($job['state'], ['planning', 'running'], true)) {
    sleep(2);
    $job = $seatlayer->seasons->retrieveSeasonBulkJob($seasonKey, $job['jobId'])['job'];
}
$block = $seatlayer->seasons->retrieveSeasonBlock($seasonKey)['block'];

// Release unsold season seats to single sale. Leave out 'at' to release now.
$seatlayer->seasons->releaseSeasonBlock($seasonKey, [
    'expectedRevision' => $block['revision'],
    'scope' => ['all' => true],
    'at' => '2026-12-01T09:00:00Z',
]);
```

- On an active season, block mode turns on by itself once every date has the seats, and
  `season.block.ready` is sent once. On a draft, `publishSeason()` turns it on.
  `season.block.released` is sent once a release has run.
- In block mode, a date cancel (`createSeasonAmendment()`, kind `cancel`) or
  `markSeasonPerformancePast()` first takes the date off every season seat. Until that finishes the
  answer is a `202`: check it with `SeatLayer\Resources\Seasons::isDateLeavePending($answer)`, then
  send the same request again after `Retry-After`.
- Refusals come through `SeatLayerException` with `errorCode`, and nothing changes. For example
  `409 season_block_packages` (more than one package), `409 season_block_on_sale` (sales have
  opened), or `409 block_not_ready` from `openSeasonSales()` before block mode is on.
- A partner's seat on a date (one a sales channel sells there) stays out of the block. It is
  listed in `$block['notFreeOnEveryDate']` with reason `channel`.

The [Season seats docs](https://docs.seatlayer.io/server-api/seasons/#season-seats) list every refusal.

## Orders, holders, event sales, and event series

- `$seatlayer->orders` lists orders across events and seasons, reads one order and its
  tickets, and lists, refunds, cancels, or resends one event's orders.
  `exportEventOrdersCsv()` returns the CSV text.
- `$seatlayer->holders` reads and updates season ticket holders, finds customers with
  `listCustomers()`, signs a holder out, lists their seasons and tickets, and sends,
  cancels, or accepts ticket transfers.
- `$seatlayer->events` also holds each event's sales settings: buyer checkout, payment
  gateway, sales readiness, preflight and transitions, booking fee, and discount codes.
- `$seatlayer->eventSeries` plans a series of dated events from a schedule, adds dates,
  and changes every upcoming date at once.

```php
$paid = $seatlayer->orders->listEventOrders($eventKey, status: 'paid');
$seatlayer->orders->refundEventOrder(
    $eventKey,
    $orderId,
    amountMinor: 500,
    idempotencyKey: "refund-{$refundId}",
);
$csv = $seatlayer->orders->exportEventOrdersCsv($eventKey); // CSV text
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

## Book reserved seats from PHP

**Buyer picks seats in the browser.** Your frontend holds them; your backend confirms the price and
books. Never price from what the browser sent you: `retrieveHold` is authoritative.

```php
$hold = $seatlayer->inventory->retrieveHold($eventKey, $holdId);
$currencies = array_values(array_unique(array_column($hold['items'], 'currency')));
if (count($currencies) !== 1) {
    throw new RuntimeException('A hold must use one currency.');
}
$currency = $currencies[0];
$total = array_sum(array_map(
    static fn (array $item) => $item['unitPrice'] * ($item['quantity'] ?? 1),
    $hold['items'],
));
// … charge $total in $currency …
$seatlayer->inventory->book($eventKey, holdId: $holdId, bookingRef: $charge->id);
```

**Your backend picks the seats.** Phone orders, box office, comps.

```php
// Payment already taken: book outright, so nothing is stranded if a second call fails.
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
and yields as it goes. It is a `Generator` rather than an array, because the point of paginating is to
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
round-trip **per event**. `listAll()` turns them off automatically, since walking a whole catalogue is
exactly when you don't want that, and you can control it explicitly:

```php
$seatlayer->events->list(limit: 50, counts: false);
```

Pass `seriesKey: 'ser_1'` to list only the dates of one event series.

## Keeping a hold alive

When an order takes longer than the checkout window (an invoice, a phone sale), extend rather than
release and re-hold. Releasing first hands the seats to whoever is racing for them in between.

```php
use SeatLayer\ConflictException;

try {
    $seatlayer->inventory->extendHold($eventKey, $holdId, ttlMs: 10 * 60_000);
} catch (ConflictException) {
    // Gone, expired, or at its renewal cap: the buyer has to re-pick.
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

`capabilities` is **required** by this SDK even though the raw API safely defaults an omitted list
to view-only (`event:view`). Keeping the argument required makes browser authority visible at every
call site. Grant the smallest set the page needs. `event:cancel` puts a booking back on sale; it
does not refund anyone. To refund an order that SeatLayer's checkout took, grant `event:refund`.

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

// Laravel: $request->getContent(), never $request->all()
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
// captured delivery stays valid indefinitely. Deduplicate on occurrenceId:
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

Every exception carries `status`, `errorCode`, `body`, and `requestId`. Quote the request id in
support requests.

> **Naming note.** The error slug is `$e->errorCode`, not `$e->code`, because PHP's base `Exception`
> already owns `$code` as an int. Other SeatLayer SDKs expose the same value as `code`.

## Reliability

**Retries and idempotency.** Reads (`GET`/`HEAD`) retry connection failures, 408, 429 and 5xx with
exponential backoff and full jitter; `Retry-After` wins when the server sends it. Forty-one
mutations use exact header replay:

- `charts->create`, `charts->copy`, `templates->instantiateTemplate`, `events->create`,
  `workspaces->create`, and `performanceGroups->create`;
- on `seasons`: `createSeason`, `updateSeason`, `deleteSeason`, `publishSeason`,
  `createSeasonPlan`, `updateSeasonPlan`, `deleteSeasonPlan`, `duplicateSeasonToLive`,
  `createSeasonPerformances`, `attachSeasonPerformances`, `deleteSeasonPerformance`,
  `markSeasonPerformancePast`, `attachSeasonPlanPerformance`, `deleteSeasonPlanPerformance`,
  `replaceSeasonPackagePrices`, `createSeasonCheckoutTest`, `createSeasonHolderImport`,
  `createSeasonHolderImportFromSeason`, `createSeasonRenewalOffers`, `createSeasonAmendment`,
  `refundSeasonOrder`, `cancelSeasonOrder`, `cancelSeasonOrderDate`,
  `refundSeasonPerformance`, `releaseSeasonLapsedOffers`, and `createSeasonRenewalCheckout`;
- `holders->updateHolder`, `holders->createHolderTransfer`, `holders->createHolderReturn`,
  `holders->createHolderRenewalCheckout`, `orders->refundEventOrder`, `orders->cancelEventOrder`, `eventSeries->createEventSeries`,
  `eventSeries->addEventSeriesDates`, and `eventSeries->updateEventSeries`.

They generate an `Idempotency-Key` when absent and reuse that key across every attempt. You can
supply a stable provisioning key instead:

```php
$seatlayer->events->create(
    $chartId,
    name: 'Spring Gala',
    idempotencyKey: "provision-event-{$eventId}",
);
```

All remaining SDK mutations are single-attempt: holds, bookings, lifecycle changes, channel
changes, event sales settings and discount codes, ticket and renewal emails, show-once secret
creation, and raw requests. Some have a server-side domain idempotency
contract, but the SDK does not retry them automatically. Reconcile bookings with their required
`bookingRef`; never retry an unknown booking outcome as though the transport had made it safe.

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

The client exposes these resources. Performance Groups cover runs, sessions, holds, and bookings;
Seasons cover catalogue, plan, performance, price, sales, buyer-session, booking, renewal,
occurrence, holder, order, reporting, outbox, and support operations.

| Resource | Methods |
| --- | --- |
| `charts` | `list` `listAll` `create` `retrieve` `update` `delete` `copy` `archive` `unarchive` `publish` |
| `templates` | `instantiateTemplate` |
| `events` | `list` `listAll` `create` `retrieve` `retrieveConfigurationBinding` `updateConfigurationBinding` `update` `delete` `updateChart` `close` `reopen` `archive` `retrieveHoldTtl` `updateHoldTtl` `listTicketReleases` `updateTicketReleases` `closeTicketRelease` `retrieveReport` `retrieveLog` `retrieveEventBuyerCheckout` `replaceEventBuyerCheckout` `retrieveEventPaymentGateway` `replaceEventPaymentGateway` `retrieveEventSalesReadiness` `preflightEventSales` `transitionEventSales` `retrieveEventBookingFee` `replaceEventBookingFee` `listEventDiscountCodes` `createEventDiscountCode` `updateEventDiscountCode` `endEventDiscountCode` `deleteEventDiscountCode` |
| `eventSeries` | `createEventSeries` `listEventSeries` `retrieveEventSeries` `addEventSeriesDates` `updateEventSeries` |
| `orders` | `listOrders` `retrieveOrder` `listOrderTickets` `listEventOrders` `retrieveEventOrder` `exportEventOrdersCsv` `refundEventOrder` `cancelEventOrder` `resendEventOrderTickets` |
| `holders` | `listCustomers` `retrieveHolder` `updateHolder` `revokeHolderSessions` `listHolderSeasons` `listHolderTickets` `retrieveHolderTicket` `createHolderTransfer` `listHolderTransfers` `cancelHolderTransfer` `acceptHolderTransfer` `createHolderReturn` `createHolderRenewalCheckout` `declineHolderRenewalOffer` |
| `channels` | `listChannels` `createChannel` `updateChannel` `updateAssignments` `listAllocation` `retrieveAccessPreview` `retrieveReport` `pause` `unpause` `archive` `createBuyerAccessSession` `listBuyerAccessSessions` `revokeBuyerAccessSession` |
| `inventory` | `hold` `holdBestAvailable` `bookBestAvailable` `extendHold` `retrieveHold` `release` `book` `boxOfficeBook` `unbook` `block` `unblock` `unblockAll` `retrieveAvailability` `updateAvailability` `listBookings` `retrieveBooking` |
| `sessions` | `createManageSession` `revokeManageSession` `createDesignerSession` `revokeDesignerSession` |
| `webhooks` | `list` `create` `update` `delete` `listDeliveries` |
| `workspaces` | `list` `create` `retrieve` `update` |
| `performanceGroups` | `list` `create` `retrieve` `delete` `activate` `close` `retrieveLifecycle` `createBuyerAccessSession` `listBuyerAccessSessions` `revokeBuyerAccessSession` `retrieveHold` `bookHold` `retrieveBooking` |
| `seasons` | 90 operations for catalogue and Plan lifecycle, performances and packages, package prices, checkout tests, sales windows, buyer access and booking, holder imports, holders, season orders, renewals, hosted renewals and renewal checkouts, occurrence amendments, returns and reclaims, bulk refunds, lapsed-offer releases and jobs, reports and CSV exports, audit, outbox, and support export |

Full reference: [SeatLayer PHP server SDK guide](https://docs.seatlayer.io/server-sdk/php/)

## Frequently asked questions

### How do I book seats from PHP?

Create a client with your secret key, obtain a hold id (either from the buyer's
browser session or by holding server-side) and call `$seatlayer->inventory->book($eventKey, holdId: ..., bookingRef: ...)`.
`bookingRef` is your own stable order id and is the join between SeatLayer
inventory and your commercial order, so the same reference identifies the booking
in Booking History and when you later cancel it. For phone orders, box office, and
comps, `$seatlayer->inventory->bookBestAvailable(...)` books outright with no browser involved.

### What does the server SDK do compared with the buyer SDK?

The buyer SDK runs where the ticket buyer is: it renders the interactive seating
chart, handles seat selection, and creates temporary holds. This server SDK is the
trusted side. It authenticates with your secret key, inspects what a hold actually
contains, prices from server-owned data, and books. Never bundle the secret key
into a browser or a mobile app. Browser surfaces get short-lived, origin-bound
tokens that you mint here.

### How do temporary holds work server-side?

A hold reserves seats against concurrent buyers for a limited window.
`$seatlayer->inventory->retrieveHold($eventKey, $holdId)` is the authoritative answer for what is held
and at what price, so charge from its `items` rather than from anything the browser
sent you. When an order runs longer than the checkout window, `$seatlayer->inventory->extendHold(...)`
renews the hold instead of releasing and re-holding, which would hand the seats to
whoever is racing for them. Bookings carry the server's exact-selection plus
`bookingRef` safeguard, but the SDK sends each booking once; reconcile an unknown
outcome before trying again.

### Can I use my own payment provider?

Yes. Nothing in this SDK takes a payment: your server charges the hold's total through the provider
you already use, then books the seats with your order id as `bookingRef`, and SeatLayer never
touches the money. If you would rather SeatLayer run the checkout for an event
(`$seatlayer->events->replaceEventBuyerCheckout()`), buyers pay through one of 14 payment gateways,
including Stripe, PayPal and Square, straight into your own account.

## Continue your PHP integration

- [Follow the PHP server SDK guide](https://docs.seatlayer.io/server-sdk/php/)
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

## Scale evidence

Benchmarked on public 100,000-, 150,000- and 200,000-seat venue fixtures on 15 September 2026: 200,000 seats chart-ready in 1.95 s, desktop, local production build. Tested at 10,000 simulated concurrent buyers on one 12,000-seat event (18 September 2026): engine-side hold latency p99 9 ms, zero server errors. Method, fixtures and run files: https://github.com/seatlayer/seatlayer-performance. Live 200,000-seat stadium demo: https://app.seatlayer.io/demo/play/century-stadium-200k.

## Development

```bash
composer install
vendor/bin/phpstan analyse   # level 8
vendor/bin/phpunit
```

## License

MIT
