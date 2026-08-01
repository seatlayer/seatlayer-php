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
    public const CAPABILITIES = ['event:view', 'event:block', 'event:cancel', 'event:reports'];

    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Mint a manage-session token for the control room.
     *
     * `capabilities` is required here even though the API defaults it. That default
     * grants all four — including event:cancel, which un-books paid inventory.
     * Granting the ability to reverse sales by forgetting an argument is not a
     * default worth inheriting.
     *
     * @param list<string> $capabilities
     * @return array<string, mixed>
     */
    public function createManageSession(
        string $eventKey,
        string $allowedOrigin,
        array $capabilities,
        ?int $expiresInSeconds = null,
    ): array {
        if ($capabilities === []) {
            throw new \InvalidArgumentException(
                'capabilities is required: pass the smallest set the page needs, e.g. ["event:view"]. '
                . 'Omitting it server-side grants event:cancel, which can reverse paid bookings.'
            );
        }

        $body = array_filter([
            'allowedOrigin' => $allowedOrigin,
            'capabilities' => $capabilities,
            'expiresInSeconds' => $expiresInSeconds,
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
     */
    public function createDesignerSession(
        string $workspaceId,
        string $chartId,
        string $allowedOrigin,
        ?string $authority = null,
        ?string $mode = null,
        ?int $expiresInSeconds = null,
    ): array {
        $body = array_filter([
            'workspaceId' => $workspaceId,
            'chartId' => $chartId,
            'allowedOrigin' => $allowedOrigin,
            'authority' => $authority,
            'mode' => $mode,
            'expiresInSeconds' => $expiresInSeconds,
        ], static fn (mixed $v): bool => $v !== null);

        /** @var array<string, mixed> */
        return (array) $this->http->post('/v1/designer/sessions', $body);
    }

    public function revokeDesignerSession(string $sessionId): mixed
    {
        return $this->http->delete('/v1/designer/sessions/' . HttpClient::encode($sessionId));
    }
}
