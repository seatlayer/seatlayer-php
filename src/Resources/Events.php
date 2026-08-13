<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use Generator;
use SeatLayer\HttpClient;

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
     * paging a whole catalogue, where you almost certainly do not.
     *
     * @return array<string, mixed>
     */
    public function list(
        ?string $workspaceId = null,
        ?string $externalRef = null,
        ?int $limit = null,
        ?string $cursor = null,
        bool $counts = true,
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
    ): Generator {
        $cursor = null;
        do {
            $page = $this->list($workspaceId, $externalRef, $limit, $cursor, $counts);
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
    ): array {
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
}
