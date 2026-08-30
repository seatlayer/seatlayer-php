<?php

declare(strict_types=1);

namespace SeatLayer\Tests;

use PHPUnit\Framework\TestCase;
use SeatLayer\SeatLayer;

final class SeasonTest extends TestCase
{
    public function testMapsAll48SeasonOperationsAndReplayClasses(): void
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

        self::assertCount(48, $calls);
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
        ], $actual);
        self::assertNull($calls[26]['body']);

        $replayIndexes = [2, 4, 5, 10, 18, 27, 29, 38];
        foreach ($calls as $index => $call) {
            if (in_array($index, $replayIndexes, true)) {
                self::assertArrayHasKey('Idempotency-Key', $call['headers']);
            } else {
                self::assertArrayNotHasKey('Idempotency-Key', $call['headers']);
            }
        }
    }
}
