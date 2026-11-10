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

    /**
     * The job voiding a cancelled date's tickets: `sbj_void_` and the cancel's `amendmentId`.
     * Cancelling a date starts it (a repeated cancel joins it); follow it with
     * `retrieveSeasonBulkJob()`. Each season ticket it voids is told by `season.ticket.voided`
     * with reason `date_cancelled`.
     */
    public static function dateCancelVoidJobId(string $amendmentId): string
    {
        return 'sbj_void_' . $amendmentId;
    }

    /**
     * The most rows one `createSeasonHolderImport()` call takes for a package of `$dates` dates.
     * Each row is fenced on every date: 100 up to 20 dates, then `floor(2300 / ($dates + 3))`,
     * 43 at 50. A call with more answers `422 invalid_rows` with `maxRows`.
     */
    public static function holderImportRowsPerCall(int $dates): int
    {
        return max(1, min(100, intdiv(2300, max(1, $dates) + 3)));
    }

    /**
     * True for the `202` a season-seats season's date cancel (`createSeasonAmendment()`) or past
     * date (`markSeasonPerformancePast()`) answers while the date is still leaving every season
     * seat: `state` `finishing`, `code` `block_date_leave_pending`, with `eventKey`, `shardsLeft`
     * and `shardCount`. It finishes by itself: send the same request again after `Retry-After` to
     * read how it ended.
     *
     * @param array<string, mixed> $answer
     */
    public static function isDateLeavePending(array $answer): bool
    {
        return ($answer['state'] ?? null) === 'finishing' && ($answer['code'] ?? null) === 'block_date_leave_pending';
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
     * Create a draft Season. `singleTickets` true makes each date also sell its own single
     * tickets once the Season is on sale; the default is Season only. `inventoryMode` `block`
     * turns season seats on for this Season; the default is `per_hold`, and anything else answers
     * `422 invalid_inventory_mode`. Every Season answer carries `inventoryMode`: how the season's
     * seats are decided, `block` once season seats are turned on for it (`inventoryMode: "block"`
     * on create or update) or it sells from its block, else `per_hold`.
     *
     * @param array{name:string,edition?:?string,eventKeys?:list<string>,sourcePerformanceGroupKeys?:list<string>,singleTickets?:bool,inventoryMode?:'block'|'per_hold'} $params
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
     * Rename a draft Season, or set how it sells.
     *
     * `checkoutMode` is who takes the money; it cannot go back to undecided and is fixed once a
     * live booking exists. The cutoffs are hours before a date starts (0 to 720) after which its
     * tickets can no longer be sent to others, or a seat given back; `null` means the club
     * default (Settings › Ticket site), or until it starts when the club has none.
     * `audience` `public` lists the Season on your SeatLayer website; an ended Season cannot
     * become public (`409 sales_ended`). `singleTickets` turned on while the Season is on sale
     * opens each upcoming date's own sales through its readiness checks; turned off, they stop.
     * The answer's `singleTicketDates` says which dates opened or stopped. Its `jobRefused`, in
     * the rare case it is there, says why the `season_singles` job could not start: the change
     * still stands, and sending `singleTickets` true again opens any date still not open. A
     * retry with the same `expectedRevision` and Idempotency-Key is not applied twice.
     * `inventoryMode` `block` turns season seats on for this Season; `per_hold` turns them off
     * again, refused with `409 season_block_exists` once seats are set aside. It does not change
     * the Season's `revision`. Refused with `409 season_block_packages` (more than one package),
     * `409 season_block_on_sale` (sales already opened without season seats),
     * `409 season_not_draft_or_active`, or `422 invalid_inventory_mode`.
     *
     * @param array{expectedRevision:int,name?:string,edition?:?string,checkoutMode?:'seatlayer'|'server',transferCutoffHours?:?int,returnCutoffHours?:?int,audience?:'embed_only'|'public',singleTickets?:bool,inventoryMode?:'block'|'per_hold'} $params
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

    /**
     * Open sales. The answer's `warnings` say what is advised but not required, such as
     * `checkout_test_missing` until a checkout test or rehearsal has passed. Refused with
     * `409 plan_not_published` or `409 checkout_mode_not_chosen` until a package is published and
     * a checkout mode is chosen, and with `409 block_not_ready` when season seats are turned on
     * (`inventoryMode` `block`) but the seats are not set aside yet.
     *
     * @return array<string, mixed>
     */
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
     * Import incumbent holders. Each row takes `rowId`, `holderRef`, `priorPlanActivationId`,
     * `priorContractRef` and `labels`, plus optional `existingBookingRef`, `holderEmail` and
     * `holderName`. A call takes at most `Seasons::holderImportRowsPerCall($dates)` rows (100 up
     * to 20 dates, 43 at 50); more answers `422 invalid_rows` with `maxRows` on the exception's
     * `body`.
     *
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
     * Make one renewal offer per eligible prior Contract. For 100 contracts or fewer the API
     * answers `201` with `offers`. Above 100 it answers `202` with `job` (a `renewal_run` job)
     * and `batchId`: follow the job with `retrieveSeasonBulkJob()`, then page the offers with
     * `listSeasonRenewalOffers()`. Retried safely with one Idempotency-Key, which answers the
     * same offers or job. It can refuse with `409 job_mismatch` or `503 season_not_configured`.
     *
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

    /**
     * One page of renewal offers, newest first. Pass `$cursor` from the previous page's
     * `nextCursor`, which is null on the last page. `$limit` is 1-100 and defaults to 100.
     *
     * @return array<string, mixed>
     */
    public function listSeasonRenewalOffers(
        string $seasonKey,
        ?string $cursor = null,
        ?int $limit = null,
    ): array {
        return $this->get($seasonKey, '/renewal-offers', array_filter([
            'cursor' => $cursor,
            'limit' => $limit,
        ], static fn (mixed $value): bool => $value !== null));
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

    /**
     * Decline a renewal offer and release its seats at once.
     *
     * `$reason` (1 to 200 characters) is kept on the offer as `declineReason`; a repeated
     * decline keeps the first reason. Null sends none.
     *
     * @return array<string, mixed>
     */
    public function declineSeasonRenewalOffer(string $seasonKey, string $offerId, ?string $reason = null): array
    {
        return $this->post(
            $seasonKey,
            '/renewal-offers/' . HttpClient::encode($offerId) . '/decline',
            array_filter(['reason' => $reason], static fn (mixed $value): bool => $value !== null),
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
     * Amend one date of a published Season. `postpone` keeps the date with no new time yet;
     * `cancel` takes it off sale for good and, with `refund` `per_date`, refunds each SeatLayer
     * order's share of the date as a job (the answer's `refundJob`). A whole-order refund is the
     * order refund. A `cancel` also voids every live ticket for the date as a `date_cancel_void`
     * job, which the answer does not name: it is
     * `Seasons::dateCancelVoidJobId($answer['amendment']['amendmentId'])`, followed with
     * `retrieveSeasonBulkJob()`. A cancelled date can't be moved (`reschedule`), postponed or
     * replaced: `409 performance_cancelled`. On a season-seats season, a `cancel` first takes the
     * date off every season seat: while that finishes the answer is a `202` (check with
     * `Seasons::isDateLeavePending()`); send the same request again to read the amendment.
     *
     * @param array{eventKey:string,kind:'reschedule'|'replace'|'cancel_exception'|'postpone'|'cancel',startsAt?:int,name?:string,planActivationId?:string,refund?:'none'|'per_date',reason?:string} $params
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

    /**
     * Dry-run or commit renewal rights derived from a previous season's holders.
     *
     * A dry run answers `200` and a commit of 100 holders or fewer `201`, both with `import`.
     * A commit of more than 100 holders answers `202` with `job` (a `holder_import` job): follow
     * it with `retrieveSeasonBulkJob()`, whose `summary` has the same counts when it completes.
     * Retried safely with one Idempotency-Key, which answers the same import or job. It can
     * refuse with `409 job_mismatch`, `503 job_busy` or `503 season_not_configured`.
     *
     * @param array{sourceSeasonId:string,successorPlanActivationId:string,dryRun?:bool} $params
     * @return array<string, mixed>
     */
    public function createSeasonHolderImportFromSeason(
        string $seasonKey,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/imports/from-season'),
            $params,
            $idempotencyKey,
        );
    }

    /**
     * List the Season's performances and package membership in every lifecycle state.
     *
     * @return array<string, mixed>
     */
    public function listSeasonPerformances(string $seasonKey): array
    {
        return $this->get($seasonKey, '/performances');
    }

    /**
     * Create performances inside a draft Season from a repeat rule.
     *
     * @param array{titlePattern:string,timezone:string,repeat:'none'|'daily'|'weekly'|'biweekly'|'dates',firstStartsAt?:int|float,count?:int,dates?:list<int|float>,venue?:?string,durationMinutes?:?int,region?:string,attachToPlanKeys?:list<string>} $params
     * @return array<string, mixed>
     */
    public function createSeasonPerformances(
        string $seasonKey,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/performances'),
            $params,
            $idempotencyKey,
        );
    }

    /**
     * Attach existing Events to a draft Season and, optionally, to its packages.
     *
     * @param array{eventKeys:list<string>,attachToPlanKeys?:list<string>} $params
     * @return array<string, mixed>
     */
    public function attachSeasonPerformances(
        string $seasonKey,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/performances/attach'),
            $params,
            $idempotencyKey,
        );
    }

    /**
     * Remove a performance from a draft Season and every package.
     *
     * @return array<string, mixed>
     */
    public function deleteSeasonPerformance(
        string $seasonKey,
        string $eventKey,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->mutationWithHeaderReplay(
            'DELETE',
            self::path($seasonKey, '/performances/' . HttpClient::encode($eventKey)),
            null,
            $idempotencyKey,
        );
    }

    /**
     * Rename a draft package or change the performances it covers.
     *
     * @param array{name?:string,eventKeys?:list<string>} $params
     * @return array<string, mixed>
     */
    public function updateSeasonPlan(
        string $seasonKey,
        string $planKey,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->mutationWithHeaderReplay(
            'PATCH',
            self::path($seasonKey, '/plans/' . HttpClient::encode($planKey)),
            $params,
            $idempotencyKey,
        );
    }

    /** Delete a draft package that is not the Season's last one. The API answers 204. */
    public function deleteSeasonPlan(string $seasonKey, string $planKey, ?string $idempotencyKey = null): void
    {
        $this->http->mutationWithHeaderReplay(
            'DELETE',
            self::path($seasonKey, '/plans/' . HttpClient::encode($planKey)),
            null,
            $idempotencyKey,
        );
    }

    /**
     * Activate the Season structure and publish every draft package in one retryable call.
     *
     * @return array<string, mixed>
     */
    public function publishSeason(string $seasonKey, ?string $idempotencyKey = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postObjectWithHeaderReplay(
            self::path($seasonKey, '/publish'),
            [],
            $idempotencyKey,
        );
    }

    /**
     * Run a server-side hold and release to prove the Season buyer path.
     *
     * @param array{planKey?:string,quantity?:int} $params
     * @return array<string, mixed>
     */
    public function createSeasonCheckoutTest(
        string $seasonKey,
        array $params = [],
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postObjectWithHeaderReplay(
            self::path($seasonKey, '/checkout-tests'),
            $params,
            $idempotencyKey,
        );
    }

    /**
     * List recent Season checkout tests, newest first.
     *
     * @return array<string, mixed>
     */
    public function listSeasonCheckoutTests(string $seasonKey): array
    {
        return $this->get($seasonKey, '/checkout-tests');
    }

    /**
     * Replace a package's price table, per chart category, in minor units. A price without a
     * `priceTypeKey` is `adult`; each category takes each type once.
     *
     * @param array{prices:list<array{categoryKey:string,priceTypeKey?:'adult'|'child'|'concession'|'senior'|'student',unitPrice:int}>,currency?:string} $params
     * @return array<string, mixed>
     */
    public function replaceSeasonPackagePrices(
        string $seasonKey,
        string $planKey,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->mutationWithHeaderReplay(
            'PUT',
            self::path($seasonKey, '/plans/' . HttpClient::encode($planKey) . '/prices'),
            $params,
            $idempotencyKey,
        );
    }

    /**
     * List every package price table for the Season. Each price carries its `priceTypeKey` and
     * `priceTypeLabel`.
     *
     * @return array<string, mixed>
     */
    public function listSeasonPrices(string $seasonKey): array
    {
        return $this->get($seasonKey, '/prices');
    }

    /**
     * Add a performance to a published package and extend its allocations.
     *
     * @return array<string, mixed>
     */
    public function attachSeasonPlanPerformance(
        string $seasonKey,
        string $planKey,
        string $eventKey,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/plans/' . HttpClient::encode($planKey) . '/performances'),
            ['eventKey' => $eventKey],
            $idempotencyKey,
        );
    }

    /**
     * Remove a performance from a published package and release its allocations.
     *
     * @return array<string, mixed>
     */
    public function deleteSeasonPlanPerformance(
        string $seasonKey,
        string $planKey,
        string $eventKey,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->mutationWithHeaderReplay(
            'DELETE',
            self::path(
                $seasonKey,
                '/plans/' . HttpClient::encode($planKey) . '/performances/' . HttpClient::encode($eventKey),
            ),
            null,
            $idempotencyKey,
        );
    }

    /**
     * Mark a performance past so it stops blocking new Season sales. A cancelled date answers
     * `409 performance_cancelled`. On a season-seats season the date first leaves every season
     * seat: while that finishes the answer is a `202` (check with `Seasons::isDateLeavePending()`).
     *
     * @return array<string, mixed>
     */
    public function markSeasonPerformancePast(
        string $seasonKey,
        string $eventKey,
        ?bool $force = null,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postObjectWithHeaderReplay(
            self::path($seasonKey, '/performances/' . HttpClient::encode($eventKey) . '/past'),
            array_filter(['force' => $force], static fn (mixed $value): bool => $value !== null),
            $idempotencyKey,
        );
    }

    /**
     * Return one Season occurrence allocation to ordinary Event inventory.
     *
     * Single-attempt: the stable `actionId` is the API's domain idempotency key.
     *
     * @param list<string> $labels
     * @param array{bookingActionId:string}|array{renewalOfferId:string} $source
     * @return array<string, mixed>
     */
    public function returnSeasonOccurrenceInventory(
        string $seasonKey,
        string $eventKey,
        string $actionId,
        string $bookingRef,
        string $planActivationId,
        array $labels,
        array $source,
    ): array {
        return $this->post($seasonKey, '/occurrences/' . HttpClient::encode($eventKey) . '/returns', [
            'actionId' => $actionId,
            'bookingRef' => $bookingRef,
            'planActivationId' => $planActivationId,
            'labels' => $labels,
            'source' => $source,
        ]);
    }

    /**
     * Reclaim returned occurrence inventory while it is still free.
     *
     * Single-attempt: the stable `actionId` is the API's domain idempotency key.
     *
     * @param list<string> $labels
     * @param array{bookingActionId:string}|array{renewalOfferId:string} $source
     * @return array<string, mixed>
     */
    public function reclaimSeasonOccurrenceInventory(
        string $seasonKey,
        string $eventKey,
        string $actionId,
        string $returnActionId,
        string $bookingRef,
        string $planActivationId,
        array $labels,
        array $source,
    ): array {
        return $this->post($seasonKey, '/occurrences/' . HttpClient::encode($eventKey) . '/reclaims', [
            'actionId' => $actionId,
            'returnActionId' => $returnActionId,
            'bookingRef' => $bookingRef,
            'planActivationId' => $planActivationId,
            'labels' => $labels,
            'source' => $source,
        ]);
    }

    /**
     * List season ticket holders, one page at a time.
     *
     * @return array<string, mixed>
     */
    public function listSeasonHolders(
        string $seasonKey,
        ?string $q = null,
        ?string $status = null,
        ?string $cursor = null,
        ?int $limit = null,
    ): array {
        return $this->get($seasonKey, '/holders', array_filter([
            'q' => $q,
            'status' => $status,
            'cursor' => $cursor,
            'limit' => $limit,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Refund a season order in full, or in part with `$amountMinor`, through its payment provider.
     *
     * @return array<string, mixed>
     */
    public function refundSeasonOrder(
        string $seasonKey,
        string $orderId,
        ?int $amountMinor = null,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postObjectWithHeaderReplay(
            self::path($seasonKey, '/orders/' . HttpClient::encode($orderId) . '/refund'),
            array_filter(['amountMinor' => $amountMinor], static fn (mixed $value): bool => $value !== null),
            $idempotencyKey,
        );
    }

    /**
     * Cancel a season order without moving money and release every date.
     *
     * @param array{reason:'lost_dispute'|'refunded_elsewhere'|'other',note?:string,refundConfirmed?:bool,usedTicketsAcknowledged?:bool,notifyBuyer?:bool} $params
     * @return array<string, mixed>
     */
    public function cancelSeasonOrder(
        string $seasonKey,
        string $orderId,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/orders/' . HttpClient::encode($orderId) . '/cancel'),
            $params,
            $idempotencyKey,
        );
    }

    /**
     * Take back one date of a season order and put its seats back on sale.
     *
     * @return array<string, mixed>
     */
    public function cancelSeasonOrderDate(
        string $seasonKey,
        string $orderId,
        string $eventKey,
        ?string $actionId = null,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postObjectWithHeaderReplay(
            self::path(
                $seasonKey,
                '/orders/' . HttpClient::encode($orderId) . '/dates/' . HttpClient::encode($eventKey) . '/cancel',
            ),
            array_filter(['actionId' => $actionId], static fn (mixed $value): bool => $value !== null),
            $idempotencyKey,
        );
    }

    /**
     * Email a season order's tickets again, optionally to another address.
     *
     * Single-attempt: the API does not promise replay, so a retry could send a second email.
     *
     * @return array<string, mixed>
     */
    public function resendSeasonOrderTickets(string $seasonKey, string $orderId, ?string $email = null): array
    {
        return $this->post(
            $seasonKey,
            '/orders/' . HttpClient::encode($orderId) . '/resend',
            array_filter(['email' => $email], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * Retrieve renewal links, payments and emails for one page of offers, newest first. Pass
     * `$cursor` from the previous page's `nextCursor`, which is null on the last page. `$limit`
     * is 1-500 and defaults to 500.
     *
     * @return array<string, mixed>
     */
    public function retrieveSeasonHostedRenewals(
        string $seasonKey,
        ?string $cursor = null,
        ?int $limit = null,
    ): array {
        return $this->get($seasonKey, '/renewal-hosted', array_filter([
            'cursor' => $cursor,
            'limit' => $limit,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Make a new renewal link for one holder; the old link stops working.
     *
     * This show-once link mint is deliberately single-attempt.
     *
     * @return array<string, mixed>
     */
    public function rotateSeasonRenewalOfferLink(string $seasonKey, string $offerId): array
    {
        return $this->post(
            $seasonKey,
            '/renewal-offers/' . HttpClient::encode($offerId) . '/link',
            [],
        );
    }

    /**
     * Set the name and email a renewal offer is sent to. Pass null for a field to clear it.
     *
     * @param array{email?:?string,name?:?string} $params
     * @return array<string, mixed>
     */
    public function updateSeasonRenewalOfferContact(string $seasonKey, string $offerId, array $params): array
    {
        return $this->post(
            $seasonKey,
            '/renewal-offers/' . HttpClient::encode($offerId) . '/contact',
            $params,
        );
    }

    /**
     * Email renewal invitations now, and reminders before the deadline. Up to 20,000 offer ids;
     * more answers `422 too_many_offer_ids`.
     *
     * @param list<string>|null $offerIds Only these offers; null means every open offer.
     * @param string|null $batchId Instead of offer ids: every open offer one createSeasonRenewalOffers batch made.
     * @return array<string, mixed>
     */
    public function createSeasonRenewalNotices(
        string $seasonKey,
        ?array $offerIds = null,
        ?string $batchId = null,
    ): array {
        return $this->post($seasonKey, '/renewal-notices', array_filter([
            'offerIds' => $offerIds,
            'batchId' => $batchId,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Send one holder a new renewal invitation.
     *
     * Single-attempt: the API does not promise replay, so a retry could send a second email.
     *
     * @return array<string, mixed>
     */
    public function resendSeasonRenewalOffer(string $seasonKey, string $offerId): array
    {
        return $this->post(
            $seasonKey,
            '/renewal-offers/' . HttpClient::encode($offerId) . '/resend',
            [],
        );
    }

    /**
     * Retrieve what renewing an offer costs, priced as the SeatLayer checkout will charge it.
     *
     * `problem` is null when SeatLayer checkout can take the renewal now; otherwise it says why
     * not.
     *
     * @return array<string, mixed>
     */
    public function retrieveSeasonRenewalQuote(string $seasonKey, string $offerId): array
    {
        return $this->get($seasonKey, '/renewal-offers/' . HttpClient::encode($offerId) . '/quote');
    }

    /**
     * Record that the holder means to renew, for a renewal taken by phone or at the box office,
     * so your own checkout can commit the offer.
     *
     * Single-attempt; a repeat answers the same offer.
     *
     * @return array<string, mixed>
     */
    public function createSeasonRenewalIntent(string $seasonKey, string $offerId): array
    {
        return $this->post(
            $seasonKey,
            '/renewal-offers/' . HttpClient::encode($offerId) . '/intent',
            [],
        );
    }

    /**
     * Start a SeatLayer checkout that renews an offer, with no hosted page.
     *
     * Send the buyer to `payment.url`; paying renews the offer. `returnUrl` must be on one of
     * the organization's allowed domains. A repeat with the same key answers the same order.
     *
     * @param array{returnUrl:string,buyerEmail?:string,buyerName?:string,provider?:string} $params
     * @return array<string, mixed>
     */
    public function createSeasonRenewalCheckout(
        string $seasonKey,
        string $offerId,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/renewal-offers/' . HttpClient::encode($offerId) . '/checkout'),
            $params,
            $idempotencyKey,
        );
    }

    /**
     * Retrieve season money, seats, holders and renewals as totals.
     *
     * @return array<string, mixed>
     */
    public function retrieveSeasonSalesReport(string $seasonKey): array
    {
        return $this->get($seasonKey, '/reports/sales');
    }

    /**
     * Retrieve the seats still free per package and per date.
     *
     * @return array<string, mixed>
     */
    public function retrieveSeasonSeatsReport(string $seasonKey): array
    {
        return $this->get($seasonKey, '/reports/seats');
    }

    /** Download every season ticket holder as CSV. Returns the CSV text as sent. */
    public function exportSeasonHoldersCsv(string $seasonKey, ?string $q = null, ?string $status = null): string
    {
        return $this->http->getText(self::path($seasonKey, '/reports/holders.csv'), array_filter([
            'q' => $q,
            'status' => $status,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /** Download every season order SeatLayer took as CSV. Returns the CSV text as sent. */
    public function exportSeasonOrdersCsv(string $seasonKey): string
    {
        return $this->http->getText(self::path($seasonKey, '/reports/orders.csv'));
    }

    /**
     * Retrieve season seats sold per package and per date.
     *
     * @return array<string, mixed>
     */
    public function retrieveSeasonSeatCounts(string $seasonKey): array
    {
        return $this->get($seasonKey, '/seat-counts');
    }

    /**
     * Refund every SeatLayer order holding a cancelled date, as a job. `per_date` refunds the
     * date's share of each order (rounded down). `full` is refused (`422
     * date_refund_per_date_only`): refund a whole order with the order refund. Retried safely
     * with one Idempotency-Key, which replays the same job.
     *
     * @param array{amount:'per_date',reason?:string} $params
     * @return array<string, mixed>
     */
    public function refundSeasonPerformance(
        string $seasonKey,
        string $eventKey,
        array $params,
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/performances/' . HttpClient::encode($eventKey) . '/refunds'),
            $params,
            $idempotencyKey,
        );
    }

    /**
     * Release the seats of every lapsed renewal offer, as a job. An offer extended before its
     * turn is skipped and keeps its seat. Answers `202` with `job`; follow it with
     * `retrieveSeasonBulkJob()`. Retried safely with one Idempotency-Key, which replays the
     * same job.
     *
     * @return array<string, mixed>
     */
    public function releaseSeasonLapsedOffers(string $seasonKey, ?string $idempotencyKey = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            self::path($seasonKey, '/renewal-offers/release-lapsed'),
            null,
            $idempotencyKey,
        );
    }

    /**
     * List the Season's bulk jobs, newest first (100 at most).
     *
     * @return array<string, mixed>
     */
    public function listSeasonBulkJobs(string $seasonKey): array
    {
        return $this->get($seasonKey, '/jobs');
    }

    /**
     * Retrieve one bulk job with its counts, its `summary` once it completes, and up to 500
     * failed items.
     *
     * @return array<string, mixed>
     */
    public function retrieveSeasonBulkJob(string $seasonKey, string $jobId): array
    {
        return $this->get($seasonKey, '/jobs/' . HttpClient::encode($jobId));
    }

    /**
     * Set the season's seats aside on every date: every seat its packages price, minus
     * `keepForSingleTickets` (sections, categories or seats). Answers `202` with `job` (a
     * `season_block_set_aside` job to follow with `retrieveSeasonBulkJob()`) and `block`. On an
     * active season block mode turns on by itself once every date has the seats
     * (`season.block.ready`); on a draft, Publish turns it on. Season seats must be on for the
     * season (`409 season_block_not_enabled` otherwise). Retried safely with one
     * Idempotency-Key, which replays the same answer.
     *
     * @param array{expectedRevision:int,keepForSingleTickets?:array{sectionIds?:list<string>,categoryKeys?:list<string>,labels?:list<string>}} $params
     * @return array<string, mixed>
     */
    public function setAsideSeasonBlock(string $seasonKey, array $params, ?string $idempotencyKey = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(self::path($seasonKey, '/block'), $params, $idempotencyKey);
    }

    /**
     * The season's seats set aside on every date: `block` with its `mode` (`block` once on, else
     * `per_hold`), `state`, `counts` and each date's `state`.
     *
     * @return array<string, mixed>
     */
    public function retrieveSeasonBlock(string $seasonKey): array
    {
        return $this->get($seasonKey, '/block');
    }

    /**
     * Release season seats to single sale: every unsold seat (`scope` `['all' => true]`), or the
     * sections, categories or seats named. `eventKeys` limits it to those dates (left out: every
     * date still to come); `at` (an ISO time) releases then instead of now. Answers `202` with
     * `job` (a `season_block_release` job to follow with `retrieveSeasonBulkJob()`) and `block`.
     * `season.block.released` is sent once it has run on every date. Retried safely with one
     * Idempotency-Key, which replays the same release.
     *
     * @param array{expectedRevision:int,scope:array{all:true}|array{sectionIds:list<string>}|array{categoryKeys:list<string>}|array{labels:list<string>},eventKeys?:list<string>,at?:string,reason?:string} $params
     * @return array<string, mixed>
     */
    public function releaseSeasonBlock(string $seasonKey, array $params, ?string $idempotencyKey = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(self::path($seasonKey, '/block/release'), $params, $idempotencyKey);
    }
}
