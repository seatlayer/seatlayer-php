<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use SeatLayer\HttpClient;

/**
 * Fixed multi-performance runs, trusted hold inspection, and host-authorized booking.
 *
 * This resource uses a secret key. Give only the one-time token returned by
 * createBuyerAccessSession() to PerformanceGroupPicker in the browser.
 */
final class PerformanceGroups
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    private static function path(string $performanceGroupKey, string $suffix = ''): string
    {
        return '/v1/performance-groups/' . HttpClient::encode($performanceGroupKey) . $suffix;
    }

    /** @return array<string, mixed> */
    public function list(
        ?string $workspaceId = null,
        ?string $externalRef = null,
        ?string $state = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/performance-groups', array_filter([
            'workspaceId' => $workspaceId,
            'externalRef' => $externalRef,
            'state' => $state,
            'limit' => $limit,
            'cursor' => $cursor,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Create a draft run. The same idempotency key safely replays the original response.
     *
     * @param list<string> $eventKeys
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        array $eventKeys,
        ?string $externalRef = null,
        ?string $idempotencyKey = null,
    ): array {
        $body = ['name' => $name, 'eventKeys' => $eventKeys];
        if ($externalRef !== null) {
            $body['externalRef'] = $externalRef;
        }

        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay('/v1/performance-groups', $body, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $performanceGroupKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(self::path($performanceGroupKey));
    }

    /** Delete a draft only; activated runs remain available for audit. */
    public function delete(string $performanceGroupKey): void
    {
        $this->http->delete(self::path($performanceGroupKey));
    }

    /**
     * Start activation. If lifecycleOperation.terminal is false, poll retrieveLifecycle().
     *
     * @return array<string, mixed>
     */
    public function activate(string $performanceGroupKey, int $expectedRevision): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(self::path($performanceGroupKey, '/activate'), [
            'expectedRevision' => $expectedRevision,
        ]);
    }

    /**
     * Stop new group sales. Poll retrieveLifecycle() until the close becomes terminal.
     *
     * @return array<string, mixed>
     */
    public function close(string $performanceGroupKey, int $expectedRevision): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(self::path($performanceGroupKey, '/close'), [
            'expectedRevision' => $expectedRevision,
        ]);
    }

    /** @return array<string, mixed> */
    public function retrieveLifecycle(string $performanceGroupKey, string $operationId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(self::path(
            $performanceGroupKey,
            '/lifecycle/' . HttpClient::encode($operationId),
        ));
    }

    /**
     * Reveal one origin-bound browser bearer. This call remains single-attempt.
     *
     * @param array<string, list<string>>|null $channelIdsByEvent
     * @return array<string, mixed>
     */
    public function createBuyerAccessSession(
        string $performanceGroupKey,
        string $allowedOrigin,
        bool $includePublic,
        ?array $channelIdsByEvent = null,
        ?int $expiresInSeconds = null,
        ?int $maxQuantity = null,
        ?string $buyerRef = null,
        ?string $partnerRef = null,
    ): array {
        $body = array_filter([
            'allowedOrigin' => $allowedOrigin,
            'includePublic' => $includePublic,
            'channelIdsByEvent' => $channelIdsByEvent,
            'expiresInSeconds' => $expiresInSeconds,
            'maxQuantity' => $maxQuantity,
            'buyerRef' => $buyerRef,
            'partnerRef' => $partnerRef,
        ], static fn (mixed $value): bool => $value !== null);

        /** @var array<string, mixed> */
        return (array) $this->http->post(self::path($performanceGroupKey, '/buyer-access-sessions'), $body);
    }

    /** @return array<string, mixed> */
    public function listBuyerAccessSessions(string $performanceGroupKey, ?int $limit = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(
            self::path($performanceGroupKey, '/buyer-access-sessions'),
            $limit === null ? null : ['limit' => $limit],
        );
    }

    /** @return array<string, mixed> */
    public function revokeBuyerAccessSession(string $performanceGroupKey, string $sessionId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->delete(self::path(
            $performanceGroupKey,
            '/buyer-access-sessions/' . HttpClient::encode($sessionId),
        ));
    }

    /** @return array<string, mixed> */
    public function retrieveHold(string $performanceGroupKey, string $operationId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(self::path(
            $performanceGroupKey,
            '/holds/' . HttpClient::encode($operationId),
        ));
    }

    /**
     * Confirm an already-authorized payment. Reuse both stable IDs and poll retrieveBooking() while pending.
     *
     * @return array<string, mixed>
     */
    public function bookHold(
        string $performanceGroupKey,
        string $operationId,
        string $bookActionId,
        string $bookingRef,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->post(self::path(
            $performanceGroupKey,
            '/holds/' . HttpClient::encode($operationId) . '/book',
        ), ['bookActionId' => $bookActionId, 'bookingRef' => $bookingRef]);
    }

    /** @return array<string, mixed> */
    public function retrieveBooking(string $performanceGroupKey, string $actionId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(self::path(
            $performanceGroupKey,
            '/bookings/' . HttpClient::encode($actionId),
        ));
    }
}
