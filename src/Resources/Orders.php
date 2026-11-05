<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use SeatLayer\HttpClient;

/** Orders SeatLayer took, across events and seasons, and one event's orders. */
final class Orders
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * List orders across events and seasons. `$from` and `$to` are epoch milliseconds.
     *
     * @return array<string, mixed>
     */
    public function listOrders(
        ?string $eventKey = null,
        ?string $seasonKey = null,
        ?string $status = null,
        ?int $from = null,
        ?int $to = null,
        ?string $cursor = null,
        ?int $limit = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/orders', array_filter([
            'eventKey' => $eventKey,
            'seasonKey' => $seasonKey,
            'status' => $status,
            'from' => $from,
            'to' => $to,
            'cursor' => $cursor,
            'limit' => $limit,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Retrieve an order and its lines.
     *
     * @return array<string, mixed>
     */
    public function retrieveOrder(string $orderId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/orders/' . HttpClient::encode($orderId));
    }

    /**
     * List an order's tickets with scan links.
     *
     * @return array<string, mixed>
     */
    public function listOrderTickets(string $orderId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/orders/' . HttpClient::encode($orderId) . '/tickets');
    }

    /**
     * List one event's orders. `$from` and `$to` are epoch milliseconds.
     *
     * @return array<string, mixed>
     */
    public function listEventOrders(
        string $eventKey,
        ?string $q = null,
        ?string $status = null,
        ?string $paidBy = null,
        ?int $from = null,
        ?int $to = null,
        ?string $cursor = null,
        ?int $limit = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/events/' . HttpClient::encode($eventKey) . '/orders', array_filter([
            'q' => $q,
            'status' => $status,
            'paidBy' => $paidBy,
            'from' => $from,
            'to' => $to,
            'cursor' => $cursor,
            'limit' => $limit,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Retrieve one event order with its guests and tickets.
     *
     * @return array<string, mixed>
     */
    public function retrieveEventOrder(string $eventKey, string $orderId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(
            '/v1/events/' . HttpClient::encode($eventKey) . '/orders/' . HttpClient::encode($orderId),
        );
    }

    /** Download one event's orders as CSV. Returns the CSV text as sent. */
    public function exportEventOrdersCsv(
        string $eventKey,
        ?string $q = null,
        ?string $status = null,
        ?string $paidBy = null,
        ?int $from = null,
        ?int $to = null,
    ): string {
        return $this->http->getText('/v1/events/' . HttpClient::encode($eventKey) . '/orders.csv', array_filter([
            'q' => $q,
            'status' => $status,
            'paidBy' => $paidBy,
            'from' => $from,
            'to' => $to,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Refund an event order in full, or in part with `$amountMinor`, through its payment provider.
     *
     * @return array<string, mixed>
     */
    public function refundEventOrder(
        string $eventKey,
        string $orderId,
        ?int $amountMinor = null,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postObjectWithHeaderReplay(
            '/v1/events/' . HttpClient::encode($eventKey) . '/orders/' . HttpClient::encode($orderId) . '/refund',
            array_filter(['amountMinor' => $amountMinor], static fn (mixed $value): bool => $value !== null),
            $idempotencyKey,
        );
    }

    /**
     * Cancel an event order without moving money.
     *
     * @param array{reason:'lost_dispute'|'refunded_elsewhere'|'other',note?:string,refundConfirmed?:bool,usedTicketsAcknowledged?:bool,notifyBuyer?:bool} $params
     * @return array<string, mixed>
     */
    public function cancelEventOrder(
        string $eventKey,
        string $orderId,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            '/v1/events/' . HttpClient::encode($eventKey) . '/orders/' . HttpClient::encode($orderId) . '/cancel',
            $params,
            $idempotencyKey,
        );
    }

    /**
     * Email an event order's tickets again, optionally to another address.
     *
     * Single-attempt: the API does not promise replay, so a retry could send a second email.
     *
     * @return array<string, mixed>
     */
    public function resendEventOrderTickets(string $eventKey, string $orderId, ?string $email = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postObject(
            '/v1/events/' . HttpClient::encode($eventKey) . '/orders/' . HttpClient::encode($orderId) . '/resend',
            array_filter(['email' => $email], static fn (mixed $value): bool => $value !== null),
        );
    }
}
