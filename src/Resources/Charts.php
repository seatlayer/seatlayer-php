<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use Generator;
use SeatLayer\HttpClient;

/**
 * Seat-map definitions that events are created from.
 *
 * Even when organisers draw their own venues in the embedded Designer, you need
 * this: createDesignerSession requires a chart id that already exists, so the
 * usual platform flow is copy a template here, then hand over a session.
 */
final class Charts
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * One page of charts. Pass `cursor` from the previous page's `nextCursor`;
     * its absence means the list is exhausted.
     *
     * @return array<string, mixed>
     */
    public function list(
        ?string $workspaceId = null,
        ?string $externalRef = null,
        bool $archived = false,
        ?int $limit = null,
        ?string $cursor = null,
    ): array {
        $query = [
            'workspaceId' => $workspaceId,
            'externalRef' => $externalRef,
            'limit' => $limit,
            'cursor' => $cursor,
        ];
        if ($archived) {
            $query['archived'] = '1';
        }

        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/charts', $query);
    }

    /**
     * Every chart, paging transparently.
     *
     * A Generator rather than an array: paginating exists to stop loading an
     * unbounded result set into memory, and returning an array would hand that
     * problem straight back to the caller.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function listAll(
        ?string $workspaceId = null,
        ?string $externalRef = null,
        bool $archived = false,
        ?int $limit = null,
    ): Generator {
        $cursor = null;
        do {
            $page = $this->list($workspaceId, $externalRef, $archived, $limit, $cursor);
            $charts = $page['charts'] ?? [];
            if (is_array($charts)) {
                foreach ($charts as $chart) {
                    if (is_array($chart)) {
                        yield $chart;
                    }
                }
            }
            $next = $page['nextCursor'] ?? null;
            $cursor = is_string($next) ? $next : null;
        } while ($cursor !== null);
    }

    /**
     * @param array<string, mixed>|null $doc
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        ?array $doc = null,
        ?string $externalRef = null,
        ?string $workspaceId = null,
        ?string $idempotencyKey = null,
    ): array {
        $body = array_filter(
            ['name' => $name, 'doc' => $doc, 'externalRef' => $externalRef, 'workspaceId' => $workspaceId],
            static fn (mixed $v): bool => $v !== null,
        );

        /** @var array<string, mixed> */
        return (array) $this->http->post('/v1/charts', $body, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $chartId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/charts/' . HttpClient::encode($chartId));
    }

    /**
     * Replace a chart document.
     *
     * `expectedUpdatedAt` is required for optimistic concurrency and is not
     * optional here either: without it two concurrent writers silently overwrite
     * each other, and a seat map is exactly the document where that loses work.
     * Read it from retrieve() immediately before writing.
     *
     * The Designer is the authoring surface. Use this for bulk programmatic edits
     * and migrations, not for drawing.
     *
     * @param array<string, mixed> $doc
     * @return array<string, mixed>
     */
    public function update(string $chartId, array $doc, int $expectedUpdatedAt, ?string $name = null): array
    {
        $body = ['doc' => $doc, 'expectedUpdatedAt' => $expectedUpdatedAt];
        if ($name !== null) {
            $body['name'] = $name;
        }

        /** @var array<string, mixed> */
        return (array) $this->http->put('/v1/charts/' . HttpClient::encode($chartId), $body);
    }

    public function delete(string $chartId): mixed
    {
        return $this->http->delete('/v1/charts/' . HttpClient::encode($chartId));
    }

    /**
     * Copy a chart — the usual way to provision a venue from a template.
     *
     * @return array<string, mixed>
     */
    public function copy(string $chartId, ?string $idempotencyKey = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            '/v1/charts/' . HttpClient::encode($chartId) . '/duplicate',
            null,
            $idempotencyKey,
        );
    }

    /** @return array<string, mixed> */
    public function archive(string $chartId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post('/v1/charts/' . HttpClient::encode($chartId) . '/archive');
    }

    /** @return array<string, mixed> */
    public function unarchive(string $chartId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post('/v1/charts/' . HttpClient::encode($chartId) . '/unarchive');
    }

    /**
     * Publish the draft. Events can only be created from a published chart.
     *
     * @return array<string, mixed>
     */
    public function publish(string $chartId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post('/v1/charts/' . HttpClient::encode($chartId) . '/publish');
    }
}
