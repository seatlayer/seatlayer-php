<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use SeatLayer\HttpClient;

/** Private allocations, reporting, and origin-bound buyer access. */
final class Channels
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    private function path(string $eventKey, string $suffix = ''): string
    {
        return '/v1/events/' . HttpClient::encode($eventKey) . '/channels' . $suffix;
    }

    /** @return array<string, mixed> */
    public function listChannels(string $eventKey, bool $includeArchived = false): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(
            $this->path($eventKey),
            $includeArchived ? ['includeArchived' => '1'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function createChannel(
        string $eventKey,
        string $name,
        ?string $color = null,
        ?string $marker = null,
        ?string $externalRef = null,
        ?string $accessIntent = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
    ): array {
        $body = self::compact([
            'name' => $name,
            'color' => $color,
            'marker' => $marker,
            'externalRef' => $externalRef,
            'accessIntent' => $accessIntent,
            'reason' => $reason,
        ]);

        /** @var array<string, mixed> */
        return (array) $this->http->post($this->path($eventKey), $body, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function updateChannel(
        string $eventKey,
        string $channelId,
        ?string $name = null,
        ?string $accessIntent = null,
        ?bool $acknowledgeLiveAccess = null,
        ?string $reason = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->patch(
            $this->path($eventKey, '/' . HttpClient::encode($channelId)),
            self::compact([
                'name' => $name,
                'accessIntent' => $accessIntent,
                'acknowledgeLiveAccess' => $acknowledgeLiveAccess,
                'reason' => $reason,
            ]),
        );
    }

    /**
     * @param list<string> $labels
     * @return array<string, mixed>
     */
    public function updateAssignments(
        string $eventKey,
        array $labels,
        int $assignmentVersion,
        ?string $targetChannelId = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
    ): array {
        $body = self::compact([
            'labels' => $labels,
            'assignmentVersion' => $assignmentVersion,
            'reason' => $reason,
        ]);
        $body['targetChannelId'] = $targetChannelId;

        /** @var array<string, mixed> */
        return (array) $this->http->post($this->path($eventKey, '/assignments'), $body, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function listAllocation(string $eventKey, ?string $afterLabel = null, ?int $limit = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get($this->path($eventKey, '/allocation'), [
            'afterLabel' => $afterLabel,
            'limit' => $limit,
        ]);
    }

    /**
     * @param list<string>|null $channelIds
     * @return array<string, mixed>
     */
    public function retrieveAccessPreview(
        string $eventKey,
        ?array $channelIds = null,
        ?bool $includePublic = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->get($this->path($eventKey, '/preview'), [
            'channelIds' => $channelIds === null ? null : implode(',', $channelIds),
            'includePublic' => $includePublic === null ? null : ($includePublic ? '1' : '0'),
        ]);
    }

    /** @return array<string, mixed> */
    public function retrieveReport(string $eventKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get($this->path($eventKey, '/report'));
    }

    /** @return array<string, mixed> */
    public function pause(string $eventKey, string $channelId, ?string $reason = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            $this->path($eventKey, '/' . HttpClient::encode($channelId) . '/pause'),
            self::compact(['reason' => $reason]),
        );
    }

    /** @return array<string, mixed> */
    public function unpause(string $eventKey, string $channelId, ?string $reason = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            $this->path($eventKey, '/' . HttpClient::encode($channelId) . '/unpause'),
            self::compact(['reason' => $reason]),
        );
    }

    /** @return array<string, mixed> */
    public function archive(
        string $eventKey,
        string $channelId,
        string $destination,
        ?string $reason = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            $this->path($eventKey, '/' . HttpClient::encode($channelId) . '/archive'),
            self::compact(['destination' => $destination, 'reason' => $reason]),
        );
    }

    /**
     * @param list<string>|null $channelIds
     * @return array<string, mixed>
     */
    public function createBuyerAccessSession(
        string $eventKey,
        bool $includePublic,
        string $allowedOrigin,
        ?array $channelIds = null,
        ?int $expiresInSeconds = null,
        ?int $maxQuantity = null,
        ?string $buyerRef = null,
        ?string $partnerRef = null,
        ?string $clientRequestId = null,
        ?string $idempotencyKey = null,
    ): array {
        $body = self::compact([
            'channelIds' => $channelIds,
            'includePublic' => $includePublic,
            'allowedOrigin' => $allowedOrigin,
            'expiresInSeconds' => $expiresInSeconds,
            'maxQuantity' => $maxQuantity,
            'buyerRef' => $buyerRef,
            'partnerRef' => $partnerRef,
            'clientRequestId' => $clientRequestId,
        ]);

        /** @var array<string, mixed> */
        return (array) $this->http->post(
            '/v1/events/' . HttpClient::encode($eventKey) . '/buyer-access-sessions',
            $body,
            $idempotencyKey,
        );
    }

    /** @return array<string, mixed> */
    public function listBuyerAccessSessions(
        string $eventKey,
        ?string $state = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->get(
            '/v1/events/' . HttpClient::encode($eventKey) . '/buyer-access-sessions',
            ['state' => $state, 'limit' => $limit, 'cursor' => $cursor],
        );
    }

    public function revokeBuyerAccessSession(string $eventKey, string $sessionId): mixed
    {
        return $this->http->delete(
            '/v1/events/' . HttpClient::encode($eventKey)
            . '/buyer-access-sessions/' . HttpClient::encode($sessionId),
        );
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function compact(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => $value !== null);
    }
}
