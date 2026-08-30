<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use SeatLayer\HttpClient;

/** Fixed Renewable Season organizer operations for trusted backends. */
final class Seasons
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    private static function path(string $seasonKey, string $suffix = ''): string
    {
        return '/v1/seasons/' . HttpClient::encode($seasonKey) . $suffix;
    }

    /**
     * @param array<string, mixed>|null $query
     * @return array<string, mixed>
     */
    private function get(string $seasonKey, string $suffix = '', ?array $query = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get(self::path($seasonKey, $suffix), $query);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function post(string $seasonKey, string $suffix, array $body): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postObject(self::path($seasonKey, $suffix), $body);
    }

    /** @return array<string, mixed> */
    public function listSeasons(
        ?string $workspaceId = null,
        ?string $structureState = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/seasons', array_filter([
            'workspaceId' => $workspaceId,
            'structureState' => $structureState,
            'limit' => $limit,
            'cursor' => $cursor,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param array{eventKeys?:list<string>,sourcePerformanceGroupKeys?:list<string>} $selection
     * @return array<string, mixed>
     */
    public function validateSeason(array $selection): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postObject('/v1/seasons/validate', $selection);
    }

    /**
     * @param array{name:string,edition?:?string,eventKeys?:list<string>,sourcePerformanceGroupKeys?:list<string>} $params
     * @return array<string, mixed>
     */
    public function createSeason(array $params, ?string $idempotencyKey = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay('/v1/seasons', $params, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieveSeason(string $seasonKey): array
    {
        return $this->get($seasonKey);
    }

    /**
     * @param array{expectedRevision:int,name?:string,edition?:?string} $params
     * @return array<string, mixed>
     */
    public function updateSeason(string $seasonKey, array $params, ?string $idempotencyKey = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->mutationWithHeaderReplay(
            'PATCH',
            self::path($seasonKey),
            $params,
            $idempotencyKey,
        );
    }

    public function deleteSeason(string $seasonKey, ?string $idempotencyKey = null): void
    {
        $this->http->mutationWithHeaderReplay('DELETE', self::path($seasonKey), null, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function activateSeason(string $seasonKey, int $expectedRevision): array
    {
        return $this->post($seasonKey, '/activate', ['expectedRevision' => $expectedRevision]);
    }

    /** @return array<string, mixed> */
    public function closeSeason(string $seasonKey, int $expectedRevision): array
    {
        return $this->post($seasonKey, '/close', ['expectedRevision' => $expectedRevision]);
    }

    /** @return array<string, mixed> */
    public function archiveSeason(string $seasonKey, int $expectedRevision): array
    {
        return $this->post($seasonKey, '/archive', ['expectedRevision' => $expectedRevision]);
    }

    /** @return array<string, mixed> */
    public function retrieveSeasonLifecycle(string $seasonKey, string $operationId): array
    {
        return $this->get($seasonKey, '/lifecycle/' . HttpClient::encode($operationId));
    }

    /**
     * @param array{name:string,eventKeys?:list<string>,sourcePerformanceGroupKeys?:list<string>} $params
     * @return array<string, mixed>
     */
    public function createSeasonPlan(
        string $seasonKey,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/plans'),
            $params,
            $idempotencyKey,
        );
    }

    /** @return array<string, mixed> */
    public function retrieveSeasonPlan(string $seasonKey, string $planKey): array
    {
        return $this->get($seasonKey, '/plans/' . HttpClient::encode($planKey));
    }

    /** @return array<string, mixed> */
    public function publishSeasonPlan(string $seasonKey, string $planKey, int $expectedRevision): array
    {
        return $this->post(
            $seasonKey,
            '/plans/' . HttpClient::encode($planKey) . '/publish',
            ['expectedRevision' => $expectedRevision],
        );
    }

    /** @return array<string, mixed> */
    public function supersedeSeasonPlan(string $seasonKey, string $planKey, int $expectedRevision): array
    {
        return $this->post(
            $seasonKey,
            '/plans/' . HttpClient::encode($planKey) . '/supersede',
            ['expectedRevision' => $expectedRevision],
        );
    }

    /** @return array<string, mixed> */
    private function sales(string $seasonKey, string $action, int $expectedRevision): array
    {
        return $this->post($seasonKey, '/sales/' . $action, ['expectedRevision' => $expectedRevision]);
    }

    /** @return array<string, mixed> */
    public function openSeasonSales(string $seasonKey, int $expectedRevision): array
    {
        return $this->sales($seasonKey, 'open', $expectedRevision);
    }

    /** @return array<string, mixed> */
    public function pauseSeasonSales(string $seasonKey, int $expectedRevision): array
    {
        return $this->sales($seasonKey, 'pause', $expectedRevision);
    }

    /** @return array<string, mixed> */
    public function resumeSeasonSales(string $seasonKey, int $expectedRevision): array
    {
        return $this->sales($seasonKey, 'resume', $expectedRevision);
    }

    /** @return array<string, mixed> */
    public function endSeasonSales(string $seasonKey, int $expectedRevision): array
    {
        return $this->sales($seasonKey, 'end', $expectedRevision);
    }

    /**
     * @param array{eventKeys:list<string>,name?:string} $params
     * @return array<string, mixed>
     */
    public function duplicateSeasonToLive(
        string $seasonKey,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/duplicate-to-live'),
            $params,
            $idempotencyKey,
        );
    }

    /**
     * This show-once credential mint is deliberately single-attempt.
     *
     * @param array{allowedOrigin:string,includePublic:bool,expiresInSeconds?:int,maxQuantity?:?int,buyerRef?:?string} $params
     * @return array<string, mixed>
     */
    public function createSeasonBuyerAccessSession(string $seasonKey, array $params): array
    {
        return $this->post($seasonKey, '/buyer-access-sessions', $params);
    }

    /** @return array<string, mixed> */
    public function listSeasonBuyerAccessSessions(string $seasonKey, ?int $limit = null): array
    {
        return $this->get(
            $seasonKey,
            '/buyer-access-sessions',
            $limit === null ? null : ['limit' => $limit],
        );
    }

    /** @return array<string, mixed> */
    public function revokeSeasonBuyerAccessSession(string $seasonKey, string $sessionId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->delete(self::path(
            $seasonKey,
            '/buyer-access-sessions/' . HttpClient::encode($sessionId),
        ));
    }

    /** @return array<string, mixed> */
    public function retrieveSeasonHold(string $seasonKey, string $operationId): array
    {
        return $this->get($seasonKey, '/holds/' . HttpClient::encode($operationId));
    }

    /** @return array<string, mixed> */
    public function bookSeasonHold(
        string $seasonKey,
        string $operationId,
        string $bookActionId,
        string $bookingRef,
    ): array {
        return $this->post($seasonKey, '/holds/' . HttpClient::encode($operationId) . '/book', [
            'bookActionId' => $bookActionId,
            'bookingRef' => $bookingRef,
        ]);
    }

    /** @return array<string, mixed> */
    public function retrieveSeasonBooking(string $seasonKey, string $actionId): array
    {
        return $this->get($seasonKey, '/bookings/' . HttpClient::encode($actionId));
    }

    /**
     * @param 'preserve'|'release' $rightDisposition
     * @return array<string, mixed>
     */
    public function cancelSeasonBooking(
        string $seasonKey,
        string $actionId,
        string $cancelActionId,
        string $bookingRef,
        string $planActivationId,
        string $rightDisposition,
    ): array {
        return $this->post($seasonKey, '/bookings/' . HttpClient::encode($actionId) . '/cancel', [
            'cancelActionId' => $cancelActionId,
            'bookingRef' => $bookingRef,
            'planActivationId' => $planActivationId,
            'rightDisposition' => $rightDisposition,
        ]);
    }

    /** @return array<string, mixed> */
    public function validateSeasonBuyerRehearsal(string $seasonKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->post(self::path($seasonKey, '/buyer-rehearsals/validate'));
    }

    /**
     * @param array{successorPlanActivationId:string,dryRun?:bool,rows:list<array<string,mixed>>} $params
     * @return array<string, mixed>
     */
    public function createSeasonHolderImport(
        string $seasonKey,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/imports'),
            $params,
            $idempotencyKey,
        );
    }

    /** @return array<string, mixed> */
    public function retrieveSeasonHolderImport(string $seasonKey, string $importId): array
    {
        return $this->get($seasonKey, '/imports/' . HttpClient::encode($importId));
    }

    /**
     * @param array{deadlineAt:int,successorPlanActivationId?:string,contractIds?:list<string>} $params
     * @return array<string, mixed>
     */
    public function createSeasonRenewalOffers(
        string $seasonKey,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/renewal-offers'),
            $params,
            $idempotencyKey,
        );
    }

    /** @return array<string, mixed> */
    public function listSeasonRenewalOffers(string $seasonKey): array
    {
        return $this->get($seasonKey, '/renewal-offers');
    }

    /** @return array<string, mixed> */
    public function retrieveSeasonRenewalOffer(string $seasonKey, string $offerId): array
    {
        return $this->get($seasonKey, '/renewal-offers/' . HttpClient::encode($offerId));
    }

    /** @return array<string, mixed> */
    public function extendSeasonRenewalOffer(string $seasonKey, string $offerId, int $deadlineAt): array
    {
        return $this->post(
            $seasonKey,
            '/renewal-offers/' . HttpClient::encode($offerId) . '/extend',
            ['deadlineAt' => $deadlineAt],
        );
    }

    /** @return array<string, mixed> */
    public function inspectSeasonRenewalOffer(string $seasonKey, string $offerId): array
    {
        return $this->get(
            $seasonKey,
            '/renewal-offers/' . HttpClient::encode($offerId) . '/inspect',
        );
    }

    /** @return array<string, mixed> */
    public function commitSeasonRenewalOffer(
        string $seasonKey,
        string $offerId,
        string $commitActionId,
        string $orderRef,
        string $bookingRef,
        string $planActivationId,
    ): array {
        return $this->post(
            $seasonKey,
            '/renewal-offers/' . HttpClient::encode($offerId) . '/commit',
            [
                'commitActionId' => $commitActionId,
                'orderRef' => $orderRef,
                'bookingRef' => $bookingRef,
                'planActivationId' => $planActivationId,
            ],
        );
    }

    /** @return array<string, mixed> */
    public function declineSeasonRenewalOffer(string $seasonKey, string $offerId): array
    {
        return $this->post(
            $seasonKey,
            '/renewal-offers/' . HttpClient::encode($offerId) . '/decline',
            [],
        );
    }

    /** @return array<string, mixed> */
    public function releaseSeasonRenewalOffer(string $seasonKey, string $offerId): array
    {
        return $this->post(
            $seasonKey,
            '/renewal-offers/' . HttpClient::encode($offerId) . '/release',
            [],
        );
    }

    /** @return array<string, mixed> */
    public function listSeasonOccurrences(string $seasonKey): array
    {
        return $this->get($seasonKey, '/occurrences');
    }

    /**
     * @param array{eventKey:string,kind:'reschedule'|'replace'|'cancel_exception',startsAt?:int,name?:string} $params
     * @return array<string, mixed>
     */
    public function createSeasonAmendment(
        string $seasonKey,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/amendments'),
            $params,
            $idempotencyKey,
        );
    }

    /** @return array<string, mixed> */
    public function listSeasonAmendments(string $seasonKey): array
    {
        return $this->get($seasonKey, '/amendments');
    }

    /** @return array<string, mixed> */
    public function retrieveSeasonAmendment(string $seasonKey, string $amendmentId): array
    {
        return $this->get($seasonKey, '/amendments/' . HttpClient::encode($amendmentId));
    }

    /** @return array<string, mixed> */
    public function retrieveSeasonReport(string $seasonKey): array
    {
        return $this->get($seasonKey, '/reports');
    }

    /** @return array<string, mixed> */
    public function listSeasonOperations(string $seasonKey): array
    {
        return $this->get($seasonKey, '/operations');
    }

    /** @return array<string, mixed> */
    public function retrieveSeasonSupportLookup(
        string $seasonKey,
        ?string $bookingRef = null,
        ?string $holderRef = null,
    ): array {
        return $this->get($seasonKey, '/support-lookups', array_filter([
            'bookingRef' => $bookingRef,
            'holderRef' => $holderRef,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /** @return array<string, mixed> */
    public function listSeasonOutbox(string $seasonKey): array
    {
        return $this->get($seasonKey, '/outbox');
    }

    /** @return array<string, mixed> */
    public function replaySeasonOutbox(string $seasonKey, string $occurrenceId): array
    {
        return $this->post(
            $seasonKey,
            '/outbox/' . HttpClient::encode($occurrenceId) . '/replay',
            [],
        );
    }

    /** @return array<string, mixed> */
    public function listSeasonAudit(string $seasonKey): array
    {
        return $this->get($seasonKey, '/audit');
    }

    /** @return array<string, mixed> */
    public function exportSeasonSupportSnapshot(string $seasonKey): array
    {
        return $this->get($seasonKey, '/export');
    }
}
