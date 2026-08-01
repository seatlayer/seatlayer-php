<?php

declare(strict_types=1);

namespace SeatLayer;

use SeatLayer\Resources\Charts;
use SeatLayer\Resources\Events;
use SeatLayer\Resources\Inventory;
use SeatLayer\Resources\Sessions;
use SeatLayer\Resources\Webhooks;
use SeatLayer\Resources\Workspaces;

/**
 * The SeatLayer client.
 *
 * Secret-key only. This package must never run anywhere a ticket buyer can reach
 * it — browser surfaces get short-lived scoped tokens minted via $sessions.
 */
final class SeatLayer
{
    public readonly Charts $charts;
    public readonly Events $events;
    public readonly Inventory $inventory;
    public readonly Sessions $sessions;
    public readonly Webhooks $webhooks;
    public readonly Workspaces $workspaces;

    /** `live` or `test`, derived from the key prefix. */
    public readonly string $mode;

    private readonly HttpClient $http;

    /**
     * @param null|callable(string, string, array<string, string>, ?string, float): array{status:int, body:string, headers:array<string,string>} $transport
     */
    public function __construct(
        string $secretKey,
        string $baseUrl = HttpClient::DEFAULT_BASE_URL,
        int $maxRetries = HttpClient::DEFAULT_MAX_RETRIES,
        float $timeout = HttpClient::DEFAULT_TIMEOUT,
        ?callable $transport = null,
    ) {
        $this->http = new HttpClient($secretKey, $baseUrl, $maxRetries, $timeout, $transport);
        $this->mode = $this->http->mode;

        $this->charts = new Charts($this->http);
        $this->events = new Events($this->http);
        $this->inventory = new Inventory($this->http);
        $this->sessions = new Sessions($this->http);
        $this->webhooks = new Webhooks($this->http);
        $this->workspaces = new Workspaces($this->http);
    }

    /** Dependency-aware readiness probe. */
    public function ready(): mixed
    {
        return $this->http->get('/health/ready');
    }

    /**
     * Escape hatch for surface this SDK does not wrap yet. Carries the same auth,
     * retries, idempotency and error mapping.
     *
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $body
     */
    public function request(
        string $method,
        string $path,
        ?array $query = null,
        ?array $body = null,
        ?string $idempotencyKey = null,
    ): mixed {
        return $this->http->request($method, $path, $query, $body, $idempotencyKey);
    }
}
