<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use SeatLayer\HttpClient;

/** Season ticket holders, the customers behind them, and holder ticket transfers, returns and renewals. */
final class Holders
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    private static function path(string $holderRef, string $suffix = ''): string
    {
        return '/v1/holders/' . HttpClient::encode($holderRef) . $suffix;
    }

    /**
     * List customers, or find one by email or holder reference.
     *
     * @return array<string, mixed>
     */
    public function listCustomers(
        ?string $email = null,
        ?string $holderRef = null,
        ?string $cursor = null,
        ?int $limit = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/customers', array_filter([
            'email' => $email,
            'holderRef' => $holderRef,
            'cursor' => $cursor,
            'limit' => $limit,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Retrieve the customer behind a holder reference.
     *
     * @return array<string, mixed>
     */
    public function retrieveHolder(string $holderRef): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(self::path($holderRef));
    }

    /**
     * Set a holder's email, name or phone. Pass null for name or phone to clear it.
     *
     * @param array{email?:string,name?:?string,phone?:?string} $params
     * @return array<string, mixed>
     */
    public function updateHolder(string $holderRef, array $params, ?string $idempotencyKey = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->mutationWithHeaderReplay(
            'PATCH',
            self::path($holderRef),
            $params,
            $idempotencyKey,
        );
    }

    /**
     * Sign a holder's customer out of the ticket site: one session, or every session when
     * `$sessionId` is null.
     *
     * @return array<string, mixed>
     */
    public function revokeHolderSessions(string $holderRef, ?string $sessionId = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postObject(
            self::path($holderRef, '/sessions/revoke'),
            array_filter(['sessionId' => $sessionId], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * List the seasons a holder holds seats in.
     *
     * @return array<string, mixed>
     */
    public function listHolderSeasons(string $holderRef): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(self::path($holderRef, '/seasons'));
    }

    /**
     * List a holder's tickets, date by date. `$from` and `$to` are epoch milliseconds.
     *
     * @return array<string, mixed>
     */
    public function listHolderTickets(
        string $holderRef,
        ?string $seasonKey = null,
        ?int $from = null,
        ?int $to = null,
        ?string $cursor = null,
        ?int $limit = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->get(self::path($holderRef, '/tickets'), array_filter([
            'seasonKey' => $seasonKey,
            'from' => $from,
            'to' => $to,
            'cursor' => $cursor,
            'limit' => $limit,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Retrieve one of a holder's tickets with its QR.
     *
     * @return array<string, mixed>
     */
    public function retrieveHolderTicket(string $holderRef, string $ticketId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(self::path($holderRef, '/tickets/' . HttpClient::encode($ticketId)));
    }

    /**
     * Send some of a holder's season tickets to an email.
     *
     * @param list<string> $ticketIds
     * @return array<string, mixed>
     */
    public function createHolderTransfer(
        string $holderRef,
        array $ticketIds,
        string $toEmail,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($holderRef, '/transfers'),
            ['ticketIds' => $ticketIds, 'toEmail' => $toEmail],
            $idempotencyKey,
        );
    }

    /**
     * List the transfers a holder sent or was sent.
     *
     * @return array<string, mixed>
     */
    public function listHolderTransfers(string $holderRef): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(self::path($holderRef, '/transfers'));
    }

    /**
     * Cancel a transfer that has not been accepted.
     *
     * @return array<string, mixed>
     */
    public function cancelHolderTransfer(string $holderRef, string $transferId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            self::path($holderRef, '/transfers/' . HttpClient::encode($transferId) . '/cancel'),
        );
    }

    /**
     * Accept a transfer for the recipient holder.
     *
     * @return array<string, mixed>
     */
    public function acceptHolderTransfer(string $holderRef, string $transferId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(
            self::path($holderRef, '/transfers/' . HttpClient::encode($transferId) . '/accept'),
        );
    }

    /**
     * Give back a holder's seat on one date, one per ticket.
     *
     * Takes 1 to 20 tickets, all of one season. A repeat with the same key answers with the
     * same return.
     *
     * @param list<string> $ticketIds
     * @return array<string, mixed>
     */
    public function createHolderReturn(
        string $holderRef,
        array $ticketIds,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($holderRef, '/returns'),
            ['ticketIds' => $ticketIds],
            $idempotencyKey,
        );
    }

    /**
     * Start a SeatLayer checkout that renews one of a holder's offers (`renewalOffer` on
     * `listHolderSeasons`).
     *
     * Send the buyer to `payment.url`. A repeat with the same key answers the same order.
     *
     * @param array{returnUrl:string,buyerEmail?:string,buyerName?:string,provider?:string} $params
     * @return array<string, mixed>
     */
    public function createHolderRenewalCheckout(
        string $holderRef,
        string $offerId,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($holderRef, '/renewal-offers/' . HttpClient::encode($offerId) . '/checkout'),
            $params,
            $idempotencyKey,
        );
    }

    /**
     * Decline one of a holder's renewal offers and release its seats.
     *
     * `$reason` (1 to 200 characters) is kept on the offer as `declineReason`; declining again
     * answers the same offer, with the first reason. Single-attempt.
     *
     * @return array<string, mixed>
     */
    public function declineHolderRenewalOffer(string $holderRef, string $offerId, ?string $reason = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postObject(
            self::path($holderRef, '/renewal-offers/' . HttpClient::encode($offerId) . '/decline'),
            array_filter(['reason' => $reason], static fn (mixed $value): bool => $value !== null),
        );
    }
}
