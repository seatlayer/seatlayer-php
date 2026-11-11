<?php

declare(strict_types=1);

namespace SeatLayer\Tests;

use PHPUnit\Framework\TestCase;
use SeatLayer\ConflictException;
use SeatLayer\RateLimitException;
use SeatLayer\Resources\Seasons;
use SeatLayer\SeatLayer;
use SeatLayer\SeatLayerException;
use SeatLayer\ValidationException;

final class SeasonTest extends TestCase
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    private array $calls = [];

    /**
     * A client whose transport records every request and answers with one fixed response.
     *
     * @param array<string, string> $responseHeaders
     */
    private function client(int $status = 200, string $body = '{}', array $responseHeaders = []): SeatLayer
    {
        $this->calls = [];
        $transport = function (
            string $method,
            string $url,
            array $headers,
            ?string $payload,
            float $_timeout,
        ) use ($status, $body, $responseHeaders): array {
            $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $payload];

            return ['status' => $status, 'body' => $body, 'headers' => $responseHeaders];
        };

        return new SeatLayer('sk_test_abc', 'https://api.seatlayer.io', 3, 30.0, $transport);
    }

    public function testMapsAll90SeasonOperationsAndReplayClasses(): void
    {
        /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> $calls */
        $calls = [];
        $transport = static function (
            string $method,
            string $url,
            array $headers,
            ?string $body,
            float $_timeout,
        ) use (&$calls): array {
            $calls[] = compact('method', 'url', 'headers', 'body');

            return ['status' => 200, 'body' => '{}', 'headers' => []];
        };
        $seasons = (new SeatLayer(
            'sk_test_abc',
            'https://api.seatlayer.io',
            3,
            30.0,
            $transport,
        ))->seasons;
        $key = 'sea/a';

        $seasons->listSeasons('ws 1', 'draft', 20, 'c/1');
        $seasons->validateSeason(['sourcePerformanceGroupKeys' => ['pg_1']]);
        $seasons->createSeason(['name' => 'Series', 'eventKeys' => ['ev_1', 'ev_2']], 'create-1');
        $seasons->retrieveSeason($key);
        $seasons->updateSeason($key, ['expectedRevision' => 1, 'name' => 'Series 2'], 'update-1');
        $seasons->deleteSeason($key, 'delete-1');
        $seasons->activateSeason($key, 1);
        $seasons->closeSeason($key, 2);
        $seasons->archiveSeason($key, 3);
        $seasons->retrieveSeasonLifecycle($key, 'life/1');
        $seasons->createSeasonPlan($key, ['name' => 'Plan', 'eventKeys' => ['ev_1', 'ev_2']], 'plan-1');
        $seasons->retrieveSeasonPlan($key, 'plan/1');
        $seasons->publishSeasonPlan($key, 'plan/1', 2);
        $seasons->supersedeSeasonPlan($key, 'plan/1', 3);
        $seasons->openSeasonSales($key, 3);
        $seasons->pauseSeasonSales($key, 4);
        $seasons->resumeSeasonSales($key, 5);
        $seasons->endSeasonSales($key, 6);
        $seasons->duplicateSeasonToLive($key, ['eventKeys' => ['live_1', 'live_2']], 'live-1');
        $seasons->createSeasonBuyerAccessSession($key, ['allowedOrigin' => 'https://tickets.example', 'includePublic' => true]);
        $seasons->listSeasonBuyerAccessSessions($key, 10);
        $seasons->revokeSeasonBuyerAccessSession($key, 'session/1');
        $seasons->retrieveSeasonHold($key, 'hold/1');
        $seasons->bookSeasonHold($key, 'hold/1', 'book_1', 'order_1');
        $seasons->retrieveSeasonBooking($key, 'book/1');
        $seasons->cancelSeasonBooking($key, 'book/1', 'cancel_1', 'order_1', 'pa_1', 'release');
        $seasons->validateSeasonBuyerRehearsal($key);
        $seasons->createSeasonHolderImport($key, ['successorPlanActivationId' => 'pa_1', 'rows' => []], 'import-1');
        $seasons->retrieveSeasonHolderImport($key, 'import/1');
        $seasons->createSeasonRenewalOffers($key, ['deadlineAt' => 123], 'offers-1');
        $seasons->listSeasonRenewalOffers($key);
        $seasons->retrieveSeasonRenewalOffer($key, 'offer/1');
        $seasons->extendSeasonRenewalOffer($key, 'offer/1', 456);
        $seasons->inspectSeasonRenewalOffer($key, 'offer/1');
        $seasons->commitSeasonRenewalOffer($key, 'offer/1', 'commit_1', 'order_1', 'book_1', 'pa_1');
        $seasons->declineSeasonRenewalOffer($key, 'offer/1');
        $seasons->releaseSeasonRenewalOffer($key, 'offer/1');
        $seasons->listSeasonOccurrences($key);
        $seasons->createSeasonAmendment($key, ['eventKey' => 'ev_1', 'kind' => 'reschedule'], 'amend-1');
        $seasons->listSeasonAmendments($key);
        $seasons->retrieveSeasonAmendment($key, 'amend/1');
        $seasons->retrieveSeasonReport($key);
        $seasons->listSeasonOperations($key);
        $seasons->retrieveSeasonSupportLookup($key, holderRef: 'holder a/b');
        $seasons->listSeasonOutbox($key);
        $seasons->replaySeasonOutbox($key, 'occurrence/1');
        $seasons->listSeasonAudit($key);
        $seasons->exportSeasonSupportSnapshot($key);
        $seasons->createSeasonHolderImportFromSeason(
            $key,
            ['sourceSeasonId' => 'sea_2026', 'successorPlanActivationId' => 'pa_1'],
            'from-season-1',
        );
        $seasons->listSeasonPerformances($key);
        $seasons->createSeasonPerformances(
            $key,
            ['titlePattern' => 'Night {n}', 'timezone' => 'Europe/London', 'repeat' => 'weekly', 'count' => 4],
            'perf-1',
        );
        $seasons->attachSeasonPerformances($key, ['eventKeys' => ['ev_1', 'ev_2']], 'attach-1');
        $seasons->deleteSeasonPerformance($key, 'ev/1', 'perf-del-1');
        $seasons->updateSeasonPlan($key, 'plan/1', ['name' => 'Full run'], 'plan-update-1');
        $seasons->deleteSeasonPlan($key, 'plan/1', 'plan-del-1');
        $seasons->publishSeason($key, 'publish-1');
        $seasons->createSeasonCheckoutTest($key, ['planKey' => 'plan_1', 'quantity' => 2], 'checkout-1');
        $seasons->listSeasonCheckoutTests($key);
        $seasons->replaceSeasonPackagePrices(
            $key,
            'plan/1',
            ['prices' => [['categoryKey' => 'stalls', 'unitPrice' => 12000]], 'currency' => 'GBP'],
            'prices-1',
        );
        $seasons->listSeasonPrices($key);
        $seasons->attachSeasonPlanPerformance($key, 'plan/1', 'ev_3', 'plan-perf-1');
        $seasons->deleteSeasonPlanPerformance($key, 'plan/1', 'ev/3', 'plan-perf-del-1');
        $seasons->markSeasonPerformancePast($key, 'ev/1', true, 'past-1');
        $seasons->returnSeasonOccurrenceInventory(
            $key,
            'ev/1',
            'return_1',
            'order_1',
            'pa_1',
            ['A-1', 'A-2'],
            ['bookingActionId' => 'book_1'],
        );
        $seasons->reclaimSeasonOccurrenceInventory(
            $key,
            'ev/1',
            'reclaim_1',
            'return_1',
            'order_1',
            'pa_1',
            ['A-1'],
            ['renewalOfferId' => 'offer_1'],
        );
        $seasons->listSeasonHolders($key, 'smith & co', 'active', 'c/2', 25);
        $seasons->refundSeasonOrder($key, 'ord/1', 500, 'refund-1');
        $seasons->cancelSeasonOrder($key, 'ord/1', ['reason' => 'refunded_elsewhere', 'notifyBuyer' => false], 'cancel-1');
        $seasons->cancelSeasonOrderDate($key, 'ord/1', 'ev/1', 'date_cancel_1', 'date-cancel-1');
        $seasons->resendSeasonOrderTickets($key, 'ord/1', 'buyer@example.com');
        $seasons->retrieveSeasonHostedRenewals($key);
        $seasons->rotateSeasonRenewalOfferLink($key, 'offer/1');
        $seasons->updateSeasonRenewalOfferContact($key, 'offer/1', ['email' => 'new@example.com', 'name' => null]);
        $seasons->createSeasonRenewalNotices($key, ['offer_1', 'offer_2']);
        $seasons->resendSeasonRenewalOffer($key, 'offer/1');
        $seasons->retrieveSeasonSalesReport($key);
        $seasons->retrieveSeasonSeatsReport($key);
        $seasons->exportSeasonHoldersCsv($key, 'smith', 'active');
        $seasons->exportSeasonOrdersCsv($key);
        $seasons->retrieveSeasonSeatCounts($key);
        $seasons->refundSeasonPerformance($key, 'ev/1', ['amount' => 'per_date', 'reason' => 'Storm'], 'refund-date-1');
        $seasons->listSeasonBulkJobs($key);
        $seasons->retrieveSeasonBulkJob($key, 'job/1');
        $seasons->retrieveSeasonRenewalQuote($key, 'offer/1');
        $seasons->createSeasonRenewalIntent($key, 'offer/1');
        $seasons->createSeasonRenewalCheckout($key, 'offer/1', [
            'returnUrl' => 'https://tickets.example/renewed',
            'buyerEmail' => 'ana@example.com',
            'buyerName' => 'Ana Silva',
            'provider' => 'stripe',
        ], 'renew-1');
        $seasons->releaseSeasonLapsedOffers($key, 'release-lapsed-1');
        // Season seats: set aside, read, release (phase 4).
        $seasons->setAsideSeasonBlock($key, ['expectedRevision' => 7, 'keepForSingleTickets' => ['labels' => ['A-6']]], 'block-1');
        $seasons->retrieveSeasonBlock($key);
        $seasons->releaseSeasonBlock($key, [
            'expectedRevision' => 7, 'scope' => ['all' => true], 'eventKeys' => ['ev/1'],
            'at' => '2026-12-01T10:00:00Z', 'reason' => 'Unsold',
        ]);

        self::assertCount(90, $calls);
        $actual = array_map(static fn (array $call): array => [
            $call['method'],
            substr($call['url'], strlen('https://api.seatlayer.io')),
        ], $calls);
        self::assertSame([
            ['GET', '/v1/seasons?workspaceId=ws+1&structureState=draft&limit=20&cursor=c%2F1'],
            ['POST', '/v1/seasons/validate'], ['POST', '/v1/seasons'],
            ['GET', '/v1/seasons/sea%2Fa'], ['PATCH', '/v1/seasons/sea%2Fa'],
            ['DELETE', '/v1/seasons/sea%2Fa'], ['POST', '/v1/seasons/sea%2Fa/activate'],
            ['POST', '/v1/seasons/sea%2Fa/close'], ['POST', '/v1/seasons/sea%2Fa/archive'],
            ['GET', '/v1/seasons/sea%2Fa/lifecycle/life%2F1'],
            ['POST', '/v1/seasons/sea%2Fa/plans'], ['GET', '/v1/seasons/sea%2Fa/plans/plan%2F1'],
            ['POST', '/v1/seasons/sea%2Fa/plans/plan%2F1/publish'],
            ['POST', '/v1/seasons/sea%2Fa/plans/plan%2F1/supersede'],
            ['POST', '/v1/seasons/sea%2Fa/sales/open'], ['POST', '/v1/seasons/sea%2Fa/sales/pause'],
            ['POST', '/v1/seasons/sea%2Fa/sales/resume'], ['POST', '/v1/seasons/sea%2Fa/sales/end'],
            ['POST', '/v1/seasons/sea%2Fa/duplicate-to-live'],
            ['POST', '/v1/seasons/sea%2Fa/buyer-access-sessions'],
            ['GET', '/v1/seasons/sea%2Fa/buyer-access-sessions?limit=10'],
            ['DELETE', '/v1/seasons/sea%2Fa/buyer-access-sessions/session%2F1'],
            ['GET', '/v1/seasons/sea%2Fa/holds/hold%2F1'],
            ['POST', '/v1/seasons/sea%2Fa/holds/hold%2F1/book'],
            ['GET', '/v1/seasons/sea%2Fa/bookings/book%2F1'],
            ['POST', '/v1/seasons/sea%2Fa/bookings/book%2F1/cancel'],
            ['POST', '/v1/seasons/sea%2Fa/buyer-rehearsals/validate'],
            ['POST', '/v1/seasons/sea%2Fa/imports'], ['GET', '/v1/seasons/sea%2Fa/imports/import%2F1'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-offers'], ['GET', '/v1/seasons/sea%2Fa/renewal-offers'],
            ['GET', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/extend'],
            ['GET', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/inspect'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/commit'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/decline'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/release'],
            ['GET', '/v1/seasons/sea%2Fa/occurrences'], ['POST', '/v1/seasons/sea%2Fa/amendments'],
            ['GET', '/v1/seasons/sea%2Fa/amendments'],
            ['GET', '/v1/seasons/sea%2Fa/amendments/amend%2F1'],
            ['GET', '/v1/seasons/sea%2Fa/reports'], ['GET', '/v1/seasons/sea%2Fa/operations'],
            ['GET', '/v1/seasons/sea%2Fa/support-lookups?holderRef=holder+a%2Fb'],
            ['GET', '/v1/seasons/sea%2Fa/outbox'],
            ['POST', '/v1/seasons/sea%2Fa/outbox/occurrence%2F1/replay'],
            ['GET', '/v1/seasons/sea%2Fa/audit'], ['GET', '/v1/seasons/sea%2Fa/export'],
            ['POST', '/v1/seasons/sea%2Fa/imports/from-season'],
            ['GET', '/v1/seasons/sea%2Fa/performances'], ['POST', '/v1/seasons/sea%2Fa/performances'],
            ['POST', '/v1/seasons/sea%2Fa/performances/attach'],
            ['DELETE', '/v1/seasons/sea%2Fa/performances/ev%2F1'],
            ['PATCH', '/v1/seasons/sea%2Fa/plans/plan%2F1'], ['DELETE', '/v1/seasons/sea%2Fa/plans/plan%2F1'],
            ['POST', '/v1/seasons/sea%2Fa/publish'],
            ['POST', '/v1/seasons/sea%2Fa/checkout-tests'], ['GET', '/v1/seasons/sea%2Fa/checkout-tests'],
            ['PUT', '/v1/seasons/sea%2Fa/plans/plan%2F1/prices'], ['GET', '/v1/seasons/sea%2Fa/prices'],
            ['POST', '/v1/seasons/sea%2Fa/plans/plan%2F1/performances'],
            ['DELETE', '/v1/seasons/sea%2Fa/plans/plan%2F1/performances/ev%2F3'],
            ['POST', '/v1/seasons/sea%2Fa/performances/ev%2F1/past'],
            ['POST', '/v1/seasons/sea%2Fa/occurrences/ev%2F1/returns'],
            ['POST', '/v1/seasons/sea%2Fa/occurrences/ev%2F1/reclaims'],
            ['GET', '/v1/seasons/sea%2Fa/holders?q=smith+%26+co&status=active&cursor=c%2F2&limit=25'],
            ['POST', '/v1/seasons/sea%2Fa/orders/ord%2F1/refund'],
            ['POST', '/v1/seasons/sea%2Fa/orders/ord%2F1/cancel'],
            ['POST', '/v1/seasons/sea%2Fa/orders/ord%2F1/dates/ev%2F1/cancel'],
            ['POST', '/v1/seasons/sea%2Fa/orders/ord%2F1/resend'],
            ['GET', '/v1/seasons/sea%2Fa/renewal-hosted'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/link'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/contact'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-notices'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/resend'],
            ['GET', '/v1/seasons/sea%2Fa/reports/sales'], ['GET', '/v1/seasons/sea%2Fa/reports/seats'],
            ['GET', '/v1/seasons/sea%2Fa/reports/holders.csv?q=smith&status=active'],
            ['GET', '/v1/seasons/sea%2Fa/reports/orders.csv'], ['GET', '/v1/seasons/sea%2Fa/seat-counts'],
            ['POST', '/v1/seasons/sea%2Fa/performances/ev%2F1/refunds'],
            ['GET', '/v1/seasons/sea%2Fa/jobs'], ['GET', '/v1/seasons/sea%2Fa/jobs/job%2F1'],
            ['GET', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/quote'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/intent'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/checkout'],
            ['POST', '/v1/seasons/sea%2Fa/renewal-offers/release-lapsed'],
            ['POST', '/v1/seasons/sea%2Fa/block'], ['GET', '/v1/seasons/sea%2Fa/block'],
            ['POST', '/v1/seasons/sea%2Fa/block/release'],
        ], $actual);
        self::assertNull($calls[26]['body']);

        $replayIndexes = [2, 4, 5, 10, 18, 27, 29, 38, 48, 50, 51, 52, 53, 54, 55, 56, 58, 60, 61, 62, 66, 67, 68, 80, 85, 86, 87, 89];
        foreach ($calls as $index => $call) {
            if (in_array($index, $replayIndexes, true)) {
                self::assertArrayHasKey('Idempotency-Key', $call['headers']);
            } else {
                self::assertArrayNotHasKey('Idempotency-Key', $call['headers']);
            }
        }
        self::assertSame('from-season-1', $calls[48]['headers']['Idempotency-Key']);
        self::assertSame('date-cancel-1', $calls[68]['headers']['Idempotency-Key']);
        self::assertSame('refund-date-1', $calls[80]['headers']['Idempotency-Key']);
        self::assertSame('{"amount":"per_date","reason":"Storm"}', $calls[80]['body']);
        self::assertNull($calls[83]['body']);
        self::assertSame('{}', $calls[84]['body']);
        self::assertSame('renew-1', $calls[85]['headers']['Idempotency-Key']);
        self::assertSame(
            '{"returnUrl":"https:\\/\\/tickets.example\\/renewed","buyerEmail":"ana@example.com",'
                . '"buyerName":"Ana Silva","provider":"stripe"}',
            $calls[85]['body'],
        );
        self::assertSame('release-lapsed-1', $calls[86]['headers']['Idempotency-Key']);
        self::assertNull($calls[86]['body']);
        self::assertSame('block-1', $calls[87]['headers']['Idempotency-Key']);
        self::assertSame('{"expectedRevision":7,"keepForSingleTickets":{"labels":["A-6"]}}', $calls[87]['body']);
        self::assertNull($calls[88]['body']);
        self::assertSame(
            '{"expectedRevision":7,"scope":{"all":true},"eventKeys":["ev\\/1"],"at":"2026-12-01T10:00:00Z","reason":"Unsold"}',
            $calls[89]['body'],
        );
    }

    public function testSeasonSeatsAnswersReachTheCaller(): void
    {
        $answer = [
            'job' => self::job('season_block_set_aside'),
            'block' => ['mode' => 'per_hold', 'state' => 'setting_aside', 'revision' => 7, 'counts' => ['setAside' => 5]],
        ];
        $seasons = $this->client(202, json_encode($answer, JSON_THROW_ON_ERROR))->seasons;
        self::assertSame($answer, $seasons->setAsideSeasonBlock('sea_1', ['expectedRevision' => 7]));
        // Without a caller key one is generated, so a retry replays the same answer.
        self::assertArrayHasKey('Idempotency-Key', $this->calls[0]['headers']);

        // A job of a kind this SDK did not know before reads as any other.
        $release = ['job' => self::job('season_block_release')];
        $seasons = $this->client(200, json_encode($release, JSON_THROW_ON_ERROR))->seasons;
        self::assertSame('season_block_release', $seasons->retrieveSeasonBulkJob('sea_1', 'sbr_1')['job']['kind']);

        $pending = [
            'state' => 'finishing', 'code' => 'block_date_leave_pending', 'retryable' => true,
            'eventKey' => 'ev_1', 'shardsLeft' => [1], 'shardCount' => 2, 'message' => 'Still leaving one shard',
        ];
        $seasons = $this->client(202, json_encode($pending, JSON_THROW_ON_ERROR), ['retry-after' => '2'])->seasons;
        self::assertTrue(Seasons::isDateLeavePending($seasons->createSeasonAmendment('sea_1', ['eventKey' => 'ev_1', 'kind' => 'cancel'])));
        self::assertTrue(Seasons::isDateLeavePending($seasons->markSeasonPerformancePast('sea_1', 'ev_1')));
        self::assertFalse(Seasons::isDateLeavePending(['amendment' => ['amendmentId' => 'am_1']]));
        self::assertFalse(Seasons::isDateLeavePending(['state' => 'finishing', 'code' => 'season_busy']));
        self::assertFalse(Seasons::isDateLeavePending(['state' => 'done', 'code' => 'block_date_leave_pending']));

        foreach (['season_block_not_enabled', 'block_exists', 'season_block_set_aside', 'season_inventory_locked', 'date_leave_in_progress'] as $code) {
            $seasons = $this->client(409, json_encode(['error' => $code, 'code' => $code, 'message' => $code], JSON_THROW_ON_ERROR))->seasons;
            try {
                $seasons->releaseSeasonBlock('sea_1', ['expectedRevision' => 7, 'scope' => ['labels' => ['A-1']]]);
                self::fail('expected ConflictException');
            } catch (ConflictException $error) {
                self::assertSame($code, $error->errorCode);
            }
        }
    }

    /**
     * A bulk job as the API answers it.
     *
     * @return array<string, mixed>
     */
    private static function job(string $kind): array
    {
        return [
            'jobId' => 'sbj_1',
            'kind' => $kind,
            'state' => 'planning',
            'counts' => ['total' => 240, 'done' => 0, 'skipped' => 0, 'failed' => 0],
            'createdAt' => 1,
            'updatedAt' => 1,
            'completedAt' => null,
        ];
    }

    public function testRenewalOfferListsSendTheirPageCursorAndLimit(): void
    {
        $page = ['offers' => [], 'truncated' => false, 'nextCursor' => null];
        $seasons = $this->client(200, json_encode($page, JSON_THROW_ON_ERROR))->seasons;

        $seasons->retrieveSeasonHostedRenewals('sea/a', 'c/1', 250);
        $seasons->listSeasonRenewalOffers('sea/a', 'c/2', 50);
        $seasons->retrieveSeasonHostedRenewals('sea_1', limit: 500);
        self::assertSame($page, $seasons->listSeasonRenewalOffers('sea_1'));

        self::assertSame([
            'https://api.seatlayer.io/v1/seasons/sea%2Fa/renewal-hosted?cursor=c%2F1&limit=250',
            'https://api.seatlayer.io/v1/seasons/sea%2Fa/renewal-offers?cursor=c%2F2&limit=50',
            'https://api.seatlayer.io/v1/seasons/sea_1/renewal-hosted?limit=500',
            'https://api.seatlayer.io/v1/seasons/sea_1/renewal-offers',
        ], array_column($this->calls, 'url'));
        self::assertSame(['GET', 'GET', 'GET', 'GET'], array_column($this->calls, 'method'));
    }

    public function testRenewalAtScaleAnswersJobs(): void
    {
        $offers = ['job' => self::job('renewal_run'), 'batchId' => 'srb_1'];
        $seasons = $this->client(202, json_encode($offers, JSON_THROW_ON_ERROR))->seasons;
        self::assertSame($offers, $seasons->createSeasonRenewalOffers('sea/a', ['deadlineAt' => 123], 'offers-1'));
        self::assertSame('offers-1', $this->calls[0]['headers']['Idempotency-Key']);

        $imported = ['job' => self::job('holder_import')];
        $seasons = $this->client(202, json_encode($imported, JSON_THROW_ON_ERROR))->seasons;
        self::assertSame($imported, $seasons->createSeasonHolderImportFromSeason(
            'sea/a',
            ['sourceSeasonId' => 'sea_2026', 'successorPlanActivationId' => 'pa_1'],
            'from-season-1',
        ));
        self::assertSame('from-season-1', $this->calls[0]['headers']['Idempotency-Key']);

        $released = ['job' => self::job('lapsed_release')];
        $seasons = $this->client(202, json_encode($released, JSON_THROW_ON_ERROR))->seasons;
        self::assertSame($released, $seasons->releaseSeasonLapsedOffers('sea/a'));
        self::assertSame('POST', $this->calls[0]['method']);
        self::assertSame(
            'https://api.seatlayer.io/v1/seasons/sea%2Fa/renewal-offers/release-lapsed',
            $this->calls[0]['url'],
        );
        self::assertNull($this->calls[0]['body']);
        // Without a caller key one is generated, so a retry replays the same job.
        self::assertArrayHasKey('Idempotency-Key', $this->calls[0]['headers']);

        $limited = $this->client(429, '{"error":"rate_limited"}', ['retry-after' => '0'])->seasons;
        try {
            $limited->releaseSeasonLapsedOffers('sea_1');
            self::fail('expected RateLimitException');
        } catch (RateLimitException) {
            self::assertCount(3, $this->calls);
            $keys = array_map(static fn (array $call): string => $call['headers']['Idempotency-Key'], $this->calls);
            self::assertCount(1, array_unique($keys));
        }
    }

    public function testSingleTicketsReachTheWireOnCreateAndUpdate(): void
    {
        $updated = [
            'season' => ['key' => 'sea_1', 'singleTickets' => true],
            'singleTicketDates' => [
                'direction' => 'open',
                'opened' => ['ev_1'],
                'closed' => [],
                'notOpened' => [],
                'jobId' => null,
            ],
        ];
        $seasons = $this->client(200, json_encode($updated, JSON_THROW_ON_ERROR))->seasons;

        $seasons->createSeason(['name' => 'Series', 'eventKeys' => ['ev_1', 'ev_2'], 'singleTickets' => true]);
        $answer = $seasons->updateSeason('sea/a', ['expectedRevision' => 5, 'singleTickets' => false], 'update-1');

        self::assertSame($updated, $answer);
        self::assertSame(['POST', 'PATCH'], array_column($this->calls, 'method'));
        self::assertSame([
            'https://api.seatlayer.io/v1/seasons',
            'https://api.seatlayer.io/v1/seasons/sea%2Fa',
        ], array_column($this->calls, 'url'));
        self::assertSame('{"name":"Series","eventKeys":["ev_1","ev_2"],"singleTickets":true}', $this->calls[0]['body']);
        self::assertSame('{"expectedRevision":5,"singleTickets":false}', $this->calls[1]['body']);
        self::assertSame('update-1', $this->calls[1]['headers']['Idempotency-Key']);
    }

    public function testInventoryModeReachesTheWireOnlyWhenGivenAndReadsBack(): void
    {
        $season = ['season' => ['key' => 'sea_1', 'revision' => 5, 'inventoryMode' => 'block']];
        $seasons = $this->client(200, json_encode($season, JSON_THROW_ON_ERROR))->seasons;

        $created = $seasons->createSeason(['name' => 'Series', 'eventKeys' => ['ev_1', 'ev_2'], 'inventoryMode' => 'block'], 'create-1');
        $seasons->createSeason(['name' => 'Series', 'eventKeys' => ['ev_1', 'ev_2']], 'create-2');
        $turnedOn = $seasons->updateSeason('sea/a', ['expectedRevision' => 5, 'inventoryMode' => 'block'], 'update-1');
        $seasons->updateSeason('sea/a', ['expectedRevision' => 5, 'inventoryMode' => 'per_hold'], 'update-2');
        $seasons->updateSeason('sea/a', ['expectedRevision' => 5, 'name' => 'Series 2'], 'update-3');
        $retrieved = $seasons->retrieveSeason('sea/a');

        self::assertSame(['POST', 'POST', 'PATCH', 'PATCH', 'PATCH', 'GET'], array_column($this->calls, 'method'));
        self::assertSame('{"name":"Series","eventKeys":["ev_1","ev_2"],"inventoryMode":"block"}', $this->calls[0]['body']);
        self::assertSame('{"name":"Series","eventKeys":["ev_1","ev_2"]}', $this->calls[1]['body']);
        self::assertSame('{"expectedRevision":5,"inventoryMode":"block"}', $this->calls[2]['body']);
        self::assertSame('{"expectedRevision":5,"inventoryMode":"per_hold"}', $this->calls[3]['body']);
        self::assertSame('{"expectedRevision":5,"name":"Series 2"}', $this->calls[4]['body']);
        self::assertSame('https://api.seatlayer.io/v1/seasons/sea%2Fa', $this->calls[5]['url']);
        self::assertSame($season, $created);
        self::assertSame('block', $turnedOn['season']['inventoryMode']);
        self::assertSame('block', $retrieved['season']['inventoryMode']);

        $list = ['seasons' => [
            ['key' => 'sea_1', 'inventoryMode' => 'block'],
            ['key' => 'sea_2', 'inventoryMode' => 'per_hold'],
        ], 'nextCursor' => null];
        $seasons = $this->client(200, json_encode($list, JSON_THROW_ON_ERROR))->seasons;
        self::assertSame(['block', 'per_hold'], array_column($seasons->listSeasons()['seasons'], 'inventoryMode'));
    }

    public function testInventoryModeRefusalsReachTheCallerWithTheirCode(): void
    {
        $refusals = [
            [409, 'season_block_packages'],
            [409, 'season_block_on_sale'],
            [409, 'season_not_draft_or_active'],
            [409, 'season_block_exists'],
            [422, 'invalid_inventory_mode'],
        ];
        foreach ($refusals as [$status, $code]) {
            $seasons = $this->client($status, json_encode(['error' => $code, 'code' => $code, 'message' => $code], JSON_THROW_ON_ERROR))->seasons;
            try {
                $seasons->updateSeason('sea_1', ['expectedRevision' => 5, 'inventoryMode' => 'block']);
                self::fail('expected SeatLayerException');
            } catch (SeatLayerException $error) {
                self::assertSame($status, $error->status);
                self::assertSame($code, $error->errorCode);
            }
        }

        $seasons = $this->client(409, json_encode(['error' => 'block_not_ready', 'code' => 'block_not_ready'], JSON_THROW_ON_ERROR))->seasons;
        try {
            $seasons->openSeasonSales('sea_1', 5);
            self::fail('expected ConflictException');
        } catch (ConflictException $error) {
            self::assertSame('block_not_ready', $error->errorCode);
        }
    }

    public function testRenewalNoticesForOneOfferBatchSendItsBatchId(): void
    {
        $answer = ['scheduled' => 240, 'withoutEmail' => 3];
        $seasons = $this->client(200, json_encode($answer, JSON_THROW_ON_ERROR))->seasons;

        self::assertSame($answer, $seasons->createSeasonRenewalNotices('sea/a', batchId: 'srb_1'));
        self::assertCount(1, $this->calls);
        self::assertSame('POST', $this->calls[0]['method']);
        self::assertSame('https://api.seatlayer.io/v1/seasons/sea%2Fa/renewal-notices', $this->calls[0]['url']);
        self::assertSame('{"batchId":"srb_1"}', $this->calls[0]['body']);
        self::assertArrayNotHasKey('Idempotency-Key', $this->calls[0]['headers']);
    }

    public function testRenewalDeclineReasonReachesTheWireAndTheCheckoutRetriesWithOneKey(): void
    {
        $seasons = $this->client()->seasons;

        $seasons->declineSeasonRenewalOffer('sea/a', 'offer/1', 'Moving away');
        $seasons->declineSeasonRenewalOffer('sea_1', 'offer_1');

        self::assertSame([
            'https://api.seatlayer.io/v1/seasons/sea%2Fa/renewal-offers/offer%2F1/decline',
            'https://api.seatlayer.io/v1/seasons/sea_1/renewal-offers/offer_1/decline',
        ], array_column($this->calls, 'url'));
        self::assertSame(['{"reason":"Moving away"}', '{}'], array_column($this->calls, 'body'));
        self::assertArrayNotHasKey('Idempotency-Key', $this->calls[0]['headers']);
        self::assertArrayNotHasKey('Idempotency-Key', $this->calls[1]['headers']);

        $limited = $this->client(429, '{"error":"rate_limited"}', ['retry-after' => '0'])->seasons;
        try {
            $limited->createSeasonRenewalCheckout('sea_1', 'offer_1', ['returnUrl' => 'https://tickets.example/r']);
            self::fail('expected RateLimitException');
        } catch (RateLimitException) {
            self::assertCount(3, $this->calls);
            $keys = array_map(static fn (array $call): string => $call['headers']['Idempotency-Key'], $this->calls);
            self::assertCount(1, array_unique($keys));
        }

        try {
            $limited->createSeasonRenewalIntent('sea_1', 'offer_1');
            self::fail('expected RateLimitException');
        } catch (RateLimitException) {
            self::assertCount(4, $this->calls);
            self::assertArrayNotHasKey('Idempotency-Key', $this->calls[3]['headers']);
        }
    }

    public function testPriceTypesSaleSettingsHolderContactsAndDateCancelsReachTheWire(): void
    {
        $seasons = $this->client()->seasons;

        $seasons->replaceSeasonPackagePrices('sea_1', 'plan_1', ['prices' => [
            ['categoryKey' => 'stalls', 'unitPrice' => 18000],
            ['categoryKey' => 'stalls', 'priceTypeKey' => 'child', 'unitPrice' => 9000],
        ]]);
        $seasons->updateSeason('sea_1', [
            'expectedRevision' => 3,
            'checkoutMode' => 'seatlayer',
            'transferCutoffHours' => 24,
            'returnCutoffHours' => null,
            'audience' => 'public',
        ]);
        $seasons->createSeasonHolderImport('sea_1', [
            'successorPlanActivationId' => 'pa_1',
            'rows' => [[
                'rowId' => 'r1',
                'holderRef' => 'h1',
                'priorPlanActivationId' => 'pa_0',
                'priorContractRef' => 'c1',
                'labels' => ['A-1'],
                'holderEmail' => 'ana@example.com',
                'holderName' => 'Ana Silva',
            ]],
        ]);
        $seasons->createSeasonAmendment(
            'sea_1',
            ['eventKey' => 'ev_night_3', 'kind' => 'cancel', 'refund' => 'per_date', 'reason' => 'Storm'],
            'night-3-storm',
        );
        $seasons->createSeasonAmendment('sea_1', ['eventKey' => 'ev_1', 'kind' => 'postpone']);
        $seasons->refundSeasonPerformance('sea_1', 'ev_1', ['amount' => 'per_date']);

        self::assertSame([
            '{"prices":[{"categoryKey":"stalls","unitPrice":18000},'
                . '{"categoryKey":"stalls","priceTypeKey":"child","unitPrice":9000}]}',
            '{"expectedRevision":3,"checkoutMode":"seatlayer","transferCutoffHours":24,"returnCutoffHours":null,'
                . '"audience":"public"}',
            '{"successorPlanActivationId":"pa_1","rows":[{"rowId":"r1","holderRef":"h1",'
                . '"priorPlanActivationId":"pa_0","priorContractRef":"c1","labels":["A-1"],'
                . '"holderEmail":"ana@example.com","holderName":"Ana Silva"}]}',
            '{"eventKey":"ev_night_3","kind":"cancel","refund":"per_date","reason":"Storm"}',
            '{"eventKey":"ev_1","kind":"postpone"}',
            '{"amount":"per_date"}',
        ], array_column($this->calls, 'body'));
        self::assertSame('night-3-storm', $this->calls[3]['headers']['Idempotency-Key']);
        // A refund without a caller key still gets one, so a retry replays the same job.
        self::assertArrayHasKey('Idempotency-Key', $this->calls[5]['headers']);
        self::assertSame('https://api.seatlayer.io/v1/seasons/sea_1/performances/ev_1/refunds', $this->calls[5]['url']);
    }

    public function testNamesTheDateCancelVoidJobAndSizesAHolderImportCallByItsDates(): void
    {
        self::assertSame('sbj_void_amd_1', Seasons::dateCancelVoidJobId('amd_1'));
        self::assertSame([100, 100, 95, 43], array_map(
            static fn (int $dates): int => Seasons::holderImportRowsPerCall($dates),
            [1, 20, 21, 50],
        ));

        $seasons = $this->client(422, json_encode([
            'error' => 'invalid_rows',
            'code' => 'invalid_rows',
            'message' => 'At most 43 rows a call for this package.',
            'maxRows' => 43,
        ], JSON_THROW_ON_ERROR))->seasons;
        try {
            $seasons->createSeasonHolderImport('sea_1', ['successorPlanActivationId' => 'spa_1', 'rows' => []]);
            self::fail('expected ValidationException');
        } catch (ValidationException $error) {
            self::assertSame(422, $error->status);
            self::assertSame('invalid_rows', $error->errorCode);
            self::assertSame(43, $error->body['maxRows']);
            self::assertCount(1, $this->calls);
        }
    }

    public function testNewSeasonOperationsSendExactBodies(): void
    {
        $seasons = $this->client()->seasons;
        $key = 'sea/a';

        $seasons->createSeasonHolderImportFromSeason(
            $key,
            ['sourceSeasonId' => 'sea_2026', 'successorPlanActivationId' => 'pa_1', 'dryRun' => true],
        );
        $seasons->createSeasonPerformances($key, [
            'titlePattern' => 'Night {n}',
            'timezone' => 'Europe/London',
            'repeat' => 'dates',
            'dates' => [1798761600000, 1799366400000],
            'venue' => null,
            'region' => 'western-europe',
            'attachToPlanKeys' => ['plan_1'],
        ]);
        $seasons->attachSeasonPerformances(
            $key,
            ['eventKeys' => ['ev_1', 'ev_2'], 'attachToPlanKeys' => ['plan_1']],
        );
        $seasons->deleteSeasonPerformance($key, 'ev/1');
        $seasons->updateSeasonPlan($key, 'plan/1', ['eventKeys' => ['ev_1', 'ev_2']]);
        $seasons->publishSeason($key);
        $seasons->createSeasonCheckoutTest($key);
        $seasons->replaceSeasonPackagePrices(
            $key,
            'plan/1',
            ['prices' => [['categoryKey' => 'stalls', 'unitPrice' => 12000]]],
        );
        $seasons->attachSeasonPlanPerformance($key, 'plan/1', 'ev_3');
        $seasons->markSeasonPerformancePast($key, 'ev/1');
        $seasons->returnSeasonOccurrenceInventory(
            $key,
            'ev/1',
            'return_1',
            'order_1',
            'pa_1',
            ['A-1'],
            ['bookingActionId' => 'book_1'],
        );
        $seasons->reclaimSeasonOccurrenceInventory(
            $key,
            'ev/1',
            'reclaim_1',
            'return_1',
            'order_1',
            'pa_1',
            ['A-1'],
            ['renewalOfferId' => 'offer_1'],
        );
        $seasons->refundSeasonOrder($key, 'ord/1');
        $seasons->cancelSeasonOrder(
            $key,
            'ord/1',
            ['reason' => 'other', 'note' => 'Chargeback lost', 'refundConfirmed' => true],
        );
        $seasons->cancelSeasonOrderDate($key, 'ord/1', 'ev/1');
        $seasons->resendSeasonOrderTickets($key, 'ord/1');
        $seasons->rotateSeasonRenewalOfferLink($key, 'offer/1');
        $seasons->updateSeasonRenewalOfferContact($key, 'offer/1', ['name' => 'Ada Holder']);
        $seasons->createSeasonRenewalNotices($key);
        $seasons->resendSeasonRenewalOffer($key, 'offer/1');
        $seasons->listSeasonHolders($key);

        self::assertSame([
            '{"sourceSeasonId":"sea_2026","successorPlanActivationId":"pa_1","dryRun":true}',
            '{"titlePattern":"Night {n}","timezone":"Europe\\/London","repeat":"dates",'
                . '"dates":[1798761600000,1799366400000],"venue":null,"region":"western-europe",'
                . '"attachToPlanKeys":["plan_1"]}',
            '{"eventKeys":["ev_1","ev_2"],"attachToPlanKeys":["plan_1"]}',
            null,
            '{"eventKeys":["ev_1","ev_2"]}',
            '{}',
            '{}',
            '{"prices":[{"categoryKey":"stalls","unitPrice":12000}]}',
            '{"eventKey":"ev_3"}',
            '{}',
            '{"actionId":"return_1","bookingRef":"order_1","planActivationId":"pa_1","labels":["A-1"],'
                . '"source":{"bookingActionId":"book_1"}}',
            '{"actionId":"reclaim_1","returnActionId":"return_1","bookingRef":"order_1",'
                . '"planActivationId":"pa_1","labels":["A-1"],"source":{"renewalOfferId":"offer_1"}}',
            '{}',
            '{"reason":"other","note":"Chargeback lost","refundConfirmed":true}',
            '{}',
            '{}',
            '{}',
            '{"name":"Ada Holder"}',
            '{}',
            '{}',
            null,
        ], array_column($this->calls, 'body'));

        // Replay-backed calls without a caller key still get a generated one.
        foreach ([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 12, 13, 14] as $index) {
            self::assertArrayHasKey('Idempotency-Key', $this->calls[$index]['headers']);
        }
        foreach ([10, 11, 15, 16, 17, 18, 19, 20] as $index) {
            self::assertArrayNotHasKey('Idempotency-Key', $this->calls[$index]['headers']);
        }
        self::assertSame('https://api.seatlayer.io/v1/seasons/sea%2Fa/holders', $this->calls[20]['url']);
    }

    public function testSeasonCsvExportsReturnTheRawText(): void
    {
        $csv = "holderRef,name\nH-1,\"Smith, Ada\"\n";
        $seasons = $this->client(200, $csv)->seasons;

        self::assertSame($csv, $seasons->exportSeasonHoldersCsv('sea/a'));
        self::assertSame($csv, $seasons->exportSeasonOrdersCsv('sea/a'));
        self::assertSame('GET', $this->calls[0]['method']);
        self::assertSame('https://api.seatlayer.io/v1/seasons/sea%2Fa/reports/holders.csv', $this->calls[0]['url']);
        self::assertStringStartsWith('text/csv', $this->calls[0]['headers']['Accept']);
        self::assertArrayNotHasKey('Idempotency-Key', $this->calls[1]['headers']);
    }

    public function testDeleteSeasonPlanAcceptsAnEmpty204(): void
    {
        $seasons = $this->client(204, '')->seasons;

        $seasons->deleteSeasonPlan('sea/a', 'plan/1', 'plan-del-1');

        self::assertCount(1, $this->calls);
        self::assertSame('DELETE', $this->calls[0]['method']);
        self::assertSame('https://api.seatlayer.io/v1/seasons/sea%2Fa/plans/plan%2F1', $this->calls[0]['url']);
        self::assertNull($this->calls[0]['body']);
        self::assertSame('plan-del-1', $this->calls[0]['headers']['Idempotency-Key']);
    }
}
