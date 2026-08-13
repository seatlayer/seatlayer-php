<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use SeatLayer\HttpClient;

/**
 * Short-lived, origin-bound browser tokens.
 *
 * The governing rule: the SDK mints tokens, widgets consume them. Your secret key
 * never reaches a browser.
 */
final class Sessions
{
    /** @var list<string> */
    public const CAPABILITIES = [
        'event:view',
        'event:block',
        'event:cancel',
        'event:reports',
        'event:channels:view',
        'event:channels:manage',
        'event:orders:read',
        'event:refund',
        'event:tickets:send',
        'event:door:view',
        'event:door:checkin',
        'event:boxoffice',
    ];

    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Mint a manage-session token for the control room.
     *
     * `capabilities` is required here even though the API defaults omission to
     * `event:view`. Making the grant explicit keeps the browser's authority
     * reviewable and prevents future server defaults from changing client intent.
     *
     * @param list<string> $capabilities
     * @return array<string, mixed>
     */
    public function createManageSession(
        string $eventKey,
        string $allowedOrigin,
        array $capabilities,
        ?int $expiresInSeconds = null,
        ?string $workspaceId = null,
    ): array {
        if ($capabilities === []) {
            throw new \InvalidArgumentException(
                'capabilities is required: pass the smallest explicit set the page needs, '
                . 'e.g. ["event:view"].'
            );
        }

        $body = array_filter([
            'allowedOrigin' => $allowedOrigin,
            'capabilities' => $capabilities,
            'expiresInSeconds' => $expiresInSeconds,
            'workspaceId' => $workspaceId,
        ], static fn (mixed $v): bool => $v !== null);

        /** @var array<string, mixed> */
        return (array) $this->http->post(
            '/v1/events/' . HttpClient::encode($eventKey) . '/manage-sessions',
            $body,
        );
    }

    public function revokeManageSession(string $eventKey, string $sessionId): mixed
    {
        return $this->http->delete(
            '/v1/events/' . HttpClient::encode($eventKey) . '/manage-sessions/' . HttpClient::encode($sessionId),
        );
    }

    /**
     * Mint a designer token so an organiser can edit a chart inside your own UI.
     * Requires a chart id that already exists — create or copy one first.
     *
     * @return array<string, mixed>
     * @param array<string, bool>|null $safeModeOptions
     * @param array<string, mixed>|null $features
     */
    public function createDesignerSession(
        string $workspaceId,
        string $chartId,
        string $allowedOrigin,
        ?string $authority = null,
        ?string $mode = null,
        ?int $expiresInSeconds = null,
        ?bool $canPublish = null,
        ?array $safeModeOptions = null,
        ?array $features = null,
    ): array {
        $body = array_filter([
            'workspaceId' => $workspaceId,
            'chartId' => $chartId,
            'allowedOrigin' => $allowedOrigin,
            'authority' => $authority,
            'mode' => $mode,
            'expiresInSeconds' => $expiresInSeconds,
            'canPublish' => $canPublish,
            'safeModeOptions' => $safeModeOptions,
            'features' => $features,
        ], static fn (mixed $v): bool => $v !== null);

        /** @var array<string, mixed> */
        return (array) $this->http->post('/v1/designer/sessions', $body);
    }

    public function revokeDesignerSession(string $sessionId): mixed
    {
        return $this->http->delete('/v1/designer/sessions/' . HttpClient::encode($sessionId));
    }
}
