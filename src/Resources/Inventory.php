<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use SeatLayer\HttpClient;

/**
 * Holds, booking, blocking, availability.
 *
 * Two complete flows, both first-class:
 *
 *   browser holds → retrieveHold for authoritative pricing → charge → book(holdId:)
 *   backend books labels directly — box office, phone sales, comps
 *
 * Never price from what the browser tells you. retrieveHold is the authoritative
 * answer, which is why it is a separate call.
 */
final class Inventory
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    private function path(string $eventKey, string $suffix): string
    {
        return '/v1/events/' . HttpClient::encode($eventKey) . $suffix;
    }

    /**
     * @param list<string>|null $labels
     * @param list<array<string, mixed>>|null $selections
     * @return array<string, mixed>
     */
    public function hold(
        string $eventKey,
        ?array $labels = null,
        ?array $selections = null,
        ?int $ttlMs = null,
        ?string $replaceHoldId = null,
        ?string $idempotencyKey = null,
    ): array {
        $body = array_filter([
            'labels' => $labels,
            'selections' => $selections,
            'ttlMs' => $ttlMs,
            'replaceHoldId' => $replaceHoldId,
        ], static fn (mixed $v): bool => $v !== null);

        /** @var array<string, mixed> */
        return (array) $this->http->post($this->path($eventKey, '/hold'), $body, $idempotencyKey);
    }

    /**
     * Ask us to pick the best free objects and hold them.
     *
     * The picker is the one the buyer widget uses, so a phone order and a web
     * order get the same answer for the same inventory. `qty` above the server cap
     * is clamped, not rejected.
     *
     * @return array<string, mixed>
     */
    public function holdBestAvailable(
        string $eventKey,
        int $qty,
        ?string $categoryKey = null,
        ?string $zoneId = null,
        ?int $ttlMs = null,
        ?string $idempotencyKey = null,
    ): array {
        $body = array_filter([
            'qty' => $qty,
            'categoryKey' => $categoryKey,
            'zoneId' => $zoneId,
            'ttlMs' => $ttlMs,
        ], static fn (mixed $v): bool => $v !== null);

        /** @var array<string, mixed> */
        return (array) $this->http->post($this->path($eventKey, '/best-available'), $body, $idempotencyKey);
    }

    /**
     * Pick and book in one call — the box-office shape.
     *
     * Prefer this over hold-then-book when payment is already taken: a failure
     * between two calls would strand inventory until the TTL expired.
     *
     * @return array<string, mixed>
     */
    public function bookBestAvailable(
        string $eventKey,
        int $qty,
        string $bookingRef,
        ?string $categoryKey = null,
        ?string $zoneId = null,
        ?string $idempotencyKey = null,
    ): array {
        $body = array_filter([
            'qty' => $qty,
            'bookingRef' => $bookingRef,
            'categoryKey' => $categoryKey,
            'zoneId' => $zoneId,
        ], static fn (mixed $v): bool => $v !== null);

        /** @var array<string, mixed> */
        return (array) $this->http->post($this->path($eventKey, '/best-available-book'), $body, $idempotencyKey);
    }

    /**
     * Push an active hold's expiry out by a fresh window before it lapses.
     *
     * Use this rather than release-and-re-hold when an order is taking longer than
     * the checkout window — invoiced sales, a phone order on hold. Releasing first
     * hands the seats to whoever is racing for them in between. A hold that is
     * gone, expired, or at its renewal cap answers 409 `cannot_extend`.
     *
     * @return array<string, mixed>
     */
    public function extendHold(string $eventKey, string $holdId, ?int $ttlMs = null): array
    {
        $body = array_filter(
            ['holdId' => $holdId, 'ttlMs' => $ttlMs],
            static fn (mixed $v): bool => $v !== null,
        );

        /** @var array<string, mixed> */
        return (array) $this->http->post($this->path($eventKey, '/extend'), $body);
    }

    /**
     * Authoritative items and prices. Charge from this, not the browser.
     *
     * @return array<string, mixed>
     */
    public function retrieveHold(string $eventKey, string $holdId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get($this->path($eventKey, '/holds/' . HttpClient::encode($holdId)));
    }

    /** @param list<string> $labels */
    public function release(string $eventKey, array $labels, string $holdId): mixed
    {
        return $this->http->post($this->path($eventKey, '/release'), ['labels' => $labels, 'holdId' => $holdId]);
    }

    /**
     * @param list<string>|null $labels
     * @return array<string, mixed>
     */
    public function book(
        string $eventKey,
        ?string $holdId = null,
        ?array $labels = null,
        ?string $bookingRef = null,
        ?string $idempotencyKey = null,
    ): array {
        $body = array_filter([
            'holdId' => $holdId,
            'labels' => $labels,
            'bookingRef' => $bookingRef,
        ], static fn (mixed $v): bool => $v !== null);

        /** @var array<string, mixed> */
        return (array) $this->http->post($this->path($eventKey, '/book'), $body, $idempotencyKey);
    }

    /**
     * @param list<string> $labels
     * @return array<string, mixed>
     */
    public function boxOfficeBook(
        string $eventKey,
        array $labels,
        string $bookingRef,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            $this->path($eventKey, '/box-book'),
            ['labels' => $labels, 'bookingRef' => $bookingRef],
            $idempotencyKey,
        );
    }

    /**
     * Reverse a booking. Requires a key with cancel authority.
     *
     * @param list<string> $labels
     */
    public function unbook(string $eventKey, array $labels): mixed
    {
        return $this->http->post($this->path($eventKey, '/unbook'), ['labels' => $labels]);
    }

    /**
     * Hold inventory back from sale (house seats, production holds).
     *
     * @param list<string> $labels
     */
    public function block(string $eventKey, array $labels): mixed
    {
        return $this->http->post($this->path($eventKey, '/block'), ['labels' => $labels]);
    }

    /** @param list<string> $labels */
    public function unblock(string $eventKey, array $labels): mixed
    {
        return $this->http->post($this->path($eventKey, '/unblock'), ['labels' => $labels]);
    }

    public function unblockAll(string $eventKey): mixed
    {
        return $this->http->post($this->path($eventKey, '/unblock-all'));
    }

    public function retrieveAvailability(string $eventKey): mixed
    {
        return $this->http->get($this->path($eventKey, '/availability'));
    }

    /** @param array<string, mixed> $fields */
    public function updateAvailability(string $eventKey, array $fields): mixed
    {
        return $this->http->post($this->path($eventKey, '/availability'), $fields);
    }
}
