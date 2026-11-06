<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use Generator;
use SeatLayer\HttpClient;
use SeatLayer\EventHostingRegion;

final class Events
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * One page of events.
     *
     * Live availability `counts` cost one round-trip per event server-side. They
     * are on by default because most callers want them; pass `counts: false` when
     * paging a whole catalogue, where you almost certainly do not. `seriesKey` lists
     * only the dates of one event series.
     *
     * @return array<string, mixed>
     */
    public function list(
        ?string $workspaceId = null,
        ?string $externalRef = null,
        ?int $limit = null,
        ?string $cursor = null,
        bool $counts = true,
        ?string $seriesKey = null,
    ): array {
        $query = [
            'workspaceId' => $workspaceId,
            'externalRef' => $externalRef,
            'limit' => $limit,
            'cursor' => $cursor,
        ];
        if (!$counts) {
            $query['counts'] = '0';
        }
        $query['seriesKey'] = $seriesKey;

        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/events', $query);
    }

    /**
     * Every event, paging transparently. Defaults to `counts: false` — you are
     * walking the whole list, so per-event availability is rarely what you want
     * and always what it costs.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function listAll(
        ?string $workspaceId = null,
        ?string $externalRef = null,
        ?int $limit = null,
        bool $counts = false,
        ?string $seriesKey = null,
    ): Generator {
        $cursor = null;
        do {
            $page = $this->list($workspaceId, $externalRef, $limit, $cursor, $counts, $seriesKey);
            $events = $page['events'] ?? [];
            if (is_array($events)) {
                foreach ($events as $event) {
                    if (is_array($event)) {
                        yield $event;
                    }
                }
            }
            $next = $page['nextCursor'] ?? null;
            $cursor = is_string($next) ? $next : null;
        } while ($cursor !== null);
    }

    /** @return array<string, mixed> */
    public function create(
        string $chartId,
        ?string $name = null,
        ?string $slug = null,
        ?int $startsAt = null,
        ?string $venue = null,
        ?string $externalRef = null,
        ?string $currency = null,
        ?string $idempotencyKey = null,
        ?string $description = null,
        ?int $endsAt = null,
        ?string $timezone = null,
        ?string $locale = null,
        ?string $posterAssetId = null,
        ?string $mode = null,
        ?string $region = null,
    ): array {
        EventHostingRegion::assert($region);
        $body = array_filter([
            'chartId' => $chartId,
            'name' => $name,
            'slug' => $slug,
            'startsAt' => $startsAt,
            'venue' => $venue,
            'externalRef' => $externalRef,
            'currency' => $currency,
            'description' => $description,
            'endsAt' => $endsAt,
            'timezone' => $timezone,
            'locale' => $locale,
            'posterAssetId' => $posterAssetId,
            'mode' => $mode,
            'region' => $region,
        ], static fn (mixed $v): bool => $v !== null);

        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay('/v1/events', $body, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $eventKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/events/' . HttpClient::encode($eventKey));
    }

    /** Read the Event's exact immutable configuration binding and audit history.
     *
     * @return array<string, mixed>
     */
    public function retrieveConfigurationBinding(string $eventKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(
            '/v1/events/' . HttpClient::encode($eventKey) . '/event-configuration',
        );
    }

    /**
     * Bind an exact published version, or pass null to detach.
     *
     * This compare-and-set mutation remains single-attempt because the public
     * operation does not promise exact response replay.
     *
     * @param array{id: string, version: int}|null $configuration
     * @return array<string, mixed>
     */
    public function updateConfigurationBinding(
        string $eventKey,
        int $expectedRevision,
        ?array $configuration,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->put(
            '/v1/events/' . HttpClient::encode($eventKey) . '/event-configuration',
            [
                'expectedRevision' => $expectedRevision,
                'configuration' => $configuration,
            ],
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function update(string $eventKey, array $fields): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->patch('/v1/events/' . HttpClient::encode($eventKey), $fields);
    }

    public function delete(string $eventKey): mixed
    {
        return $this->http->delete('/v1/events/' . HttpClient::encode($eventKey));
    }

    /**
     * Upload raw PNG, JPEG, or WebP poster bytes (maximum 5 MiB).
     *
     * @return array<string, mixed>
     */
    public function updatePoster(
        string $eventKey,
        string $bytes,
        string $contentType = 'application/octet-stream',
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->putBinary(
            '/v1/events/' . HttpClient::encode($eventKey) . '/poster',
            $bytes,
            $contentType,
        );
    }

    /** @return array<string, mixed> */
    public function deletePoster(string $eventKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->delete('/v1/events/' . HttpClient::encode($eventKey) . '/poster');
    }

    /** Move a live event onto the latest published version of its chart. */
    public function updateChart(
        string $eventKey,
        ?bool $acknowledgeDroppedAssignments = null,
        ?string $reason = null,
    ): mixed
    {
        return $this->http->post(
            '/v1/events/' . HttpClient::encode($eventKey) . '/update-chart',
            array_filter([
                'acknowledgeDroppedAssignments' => $acknowledgeDroppedAssignments,
                'reason' => $reason,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    /** Stop buyer sales. Existing holds keep their TTL. */
    public function close(string $eventKey): mixed
    {
        return $this->http->post('/v1/events/' . HttpClient::encode($eventKey) . '/close');
    }

    /**
     * Open a paused event's sales again. A date its season cancelled never opens again:
     * `409 season_date_cancelled`.
     */
    public function reopen(string $eventKey): mixed
    {
        return $this->http->post('/v1/events/' . HttpClient::encode($eventKey) . '/reopen');
    }

    public function archive(string $eventKey): mixed
    {
        return $this->http->post('/v1/events/' . HttpClient::encode($eventKey) . '/archive');
    }

    public function retrieveHoldTtl(string $eventKey): mixed
    {
        return $this->http->get('/v1/events/' . HttpClient::encode($eventKey) . '/hold-ttl');
    }

    public function updateHoldTtl(string $eventKey, ?int $holdTtlMs): mixed
    {
        return $this->http->post(
            '/v1/events/' . HttpClient::encode($eventKey) . '/hold-ttl',
            ['holdTtlMs' => $holdTtlMs],
        );
    }

    /** @return array<string, mixed> */
    public function listTicketReleases(string $eventKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/events/' . HttpClient::encode($eventKey) . '/releases');
    }

    /**
     * Replace every ticket release for an event.
     *
     * This is deliberately single-attempt: unlike template instantiation, the
     * route does not promise exact idempotent-response replay.
     *
     * @param list<array<string, mixed>> $releases
     * @return array<string, mixed>
     */
    public function updateTicketReleases(string $eventKey, array $releases): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->put(
            '/v1/events/' . HttpClient::encode($eventKey) . '/releases',
            ['releases' => $releases],
        );
    }

    /**
     * Close one release while preserving its audit provenance.
     *
     * @return array<string, mixed>
     */
    public function closeTicketRelease(string $eventKey, string $releaseId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            '/v1/events/' . HttpClient::encode($eventKey) . '/releases/' . HttpClient::encode($releaseId) . '/close',
        );
    }

    public function retrieveReport(string $eventKey): mixed
    {
        return $this->http->get('/v1/events/' . HttpClient::encode($eventKey) . '/report');
    }

    public function retrieveLog(string $eventKey, ?int $limit = null, ?int $before = null): mixed
    {
        return $this->http->get(
            '/v1/events/' . HttpClient::encode($eventKey) . '/log',
            ['limit' => $limit, 'before' => $before],
        );
    }

    /**
     * Retrieve who takes payment for an event, and what blocks a change.
     *
     * @return array<string, mixed>
     */
    public function retrieveEventBuyerCheckout(string $eventKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/events/' . HttpClient::encode($eventKey) . '/buyer-checkout');
    }

    /**
     * Choose SeatLayer checkout or your own for an event.
     *
     * A compare-and-set on `$expectedRevision`, sent once.
     *
     * @param 'seatlayer'|'integrator' $buyerCheckout
     * @return array<string, mixed>
     */
    public function replaceEventBuyerCheckout(string $eventKey, string $buyerCheckout, int $expectedRevision): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->put(
            '/v1/events/' . HttpClient::encode($eventKey) . '/buyer-checkout',
            ['buyerCheckout' => $buyerCheckout, 'expectedRevision' => $expectedRevision],
        );
    }

    /**
     * Retrieve an event's payment gateway and the ones it could use, optionally checked
     * against another currency.
     *
     * @return array<string, mixed>
     */
    public function retrieveEventPaymentGateway(string $eventKey, ?string $currency = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(
            '/v1/events/' . HttpClient::encode($eventKey) . '/payment-gateway',
            array_filter(['currency' => $currency], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * Assign one of the organization's connected gateways to an event. Sent once.
     *
     * @param array{connectionId?:?string,provider?:?string} $params
     * @return array<string, mixed>
     */
    public function replaceEventPaymentGateway(string $eventKey, array $params = []): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->putObject(
            '/v1/events/' . HttpClient::encode($eventKey) . '/payment-gateway',
            $params,
        );
    }

    /**
     * Retrieve what an event still needs before its next sales step.
     *
     * @return array<string, mixed>
     */
    public function retrieveEventSalesReadiness(string $eventKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/events/' . HttpClient::encode($eventKey) . '/sales/readiness');
    }

    /**
     * Record a test order as the event's sales preflight. Sent once.
     *
     * @return array<string, mixed>
     */
    public function preflightEventSales(string $eventKey, string $orderId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            '/v1/events/' . HttpClient::encode($eventKey) . '/sales/preflight',
            ['orderId' => $orderId],
        );
    }

    /**
     * Move an event's sales to testable, live, paused or ended.
     *
     * A compare-and-set on `$expectedRevision`, sent once. A date its season cancelled never
     * opens again: moving it to `testable` or `live` answers `409 season_date_cancelled`.
     *
     * @param 'setup'|'testable'|'live'|'paused'|'ended' $to
     * @return array<string, mixed>
     */
    public function transitionEventSales(
        string $eventKey,
        string $to,
        int $expectedRevision,
        ?string $reason = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            '/v1/events/' . HttpClient::encode($eventKey) . '/sales/transition',
            array_filter([
                'to' => $to,
                'expectedRevision' => $expectedRevision,
                'reason' => $reason,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * Retrieve an event's booking fee.
     *
     * @return array<string, mixed>
     */
    public function retrieveEventBookingFee(string $eventKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/events/' . HttpClient::encode($eventKey) . '/booking-fee');
    }

    /**
     * Set an event's booking fee in minor units, or pass null to clear it. Sent once.
     *
     * @return array<string, mixed>
     */
    public function replaceEventBookingFee(string $eventKey, ?int $bookingFeeMinor): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->put(
            '/v1/events/' . HttpClient::encode($eventKey) . '/booking-fee',
            ['bookingFeeMinor' => $bookingFeeMinor],
        );
    }

    /**
     * List an event's discount codes.
     *
     * @return array<string, mixed>
     */
    public function listEventDiscountCodes(string $eventKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/events/' . HttpClient::encode($eventKey) . '/discount-codes');
    }

    /**
     * Create a discount code for an event. Sent once.
     *
     * @param array{code:string,kind:'percent'|'amount',percent?:int,amountMinor?:int,startsAt?:?int,endsAt?:?int,maxUses?:?int,active?:bool} $params
     * @return array<string, mixed>
     */
    public function createEventDiscountCode(string $eventKey, array $params): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            '/v1/events/' . HttpClient::encode($eventKey) . '/discount-codes',
            $params,
        );
    }

    /**
     * Change or pause a discount code. Sent once.
     *
     * @param array{code?:string,kind?:'percent'|'amount',percent?:int,amountMinor?:int,startsAt?:?int,endsAt?:?int,maxUses?:?int,active?:bool,state?:'active'|'paused'} $params
     * @return array<string, mixed>
     */
    public function updateEventDiscountCode(string $eventKey, string $codeId, array $params): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->patch(
            '/v1/events/' . HttpClient::encode($eventKey) . '/discount-codes/' . HttpClient::encode($codeId),
            $params,
        );
    }

    /**
     * End a discount code for good.
     *
     * @return array<string, mixed>
     */
    public function endEventDiscountCode(string $eventKey, string $codeId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            '/v1/events/' . HttpClient::encode($eventKey) . '/discount-codes/' . HttpClient::encode($codeId) . '/end',
        );
    }

    /** Delete a discount code nobody has used. The API answers 204. */
    public function deleteEventDiscountCode(string $eventKey, string $codeId): void
    {
        $this->http->delete(
            '/v1/events/' . HttpClient::encode($eventKey) . '/discount-codes/' . HttpClient::encode($codeId),
        );
    }
}
