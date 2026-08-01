<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use SeatLayer\HttpClient;

final class Webhooks
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /** @return array<string, mixed> */
    public function list(): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/webhooks');
    }

    /**
     * @param list<string> $events
     * @return array<string, mixed>
     */
    public function create(string $url, array $events): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post('/v1/webhooks', ['url' => $url, 'events' => $events]);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function update(string $webhookId, array $fields): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->patch('/v1/webhooks/' . HttpClient::encode($webhookId), $fields);
    }

    public function delete(string $webhookId): mixed
    {
        return $this->http->delete('/v1/webhooks/' . HttpClient::encode($webhookId));
    }

    /** @return array<string, mixed> */
    public function listDeliveries(string $webhookId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/webhooks/' . HttpClient::encode($webhookId) . '/deliveries');
    }
}
