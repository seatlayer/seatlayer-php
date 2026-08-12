<?php

declare(strict_types=1);

namespace SeatLayer\Tests;

use PHPUnit\Framework\TestCase;
use SeatLayer\AuthException;
use SeatLayer\ConflictException;
use SeatLayer\RateLimitException;
use SeatLayer\SeatLayer;
use SeatLayer\ValidationException;

/** Client behaviour: auth, idempotency, retry, error mapping. */
final class ClientTest extends TestCase
{
    /** @var list<array{method:string, url:string, headers:array<string,string>, body:?string}> */
    private array $calls = [];

    /**
     * Replay a queue of responses and record every request.
     *
     * @param list<array{status:int, body?:array<string,mixed>, headers?:array<string,string>}> $responses
     */
    private function client(array $responses, int $maxRetries = 3): SeatLayer
    {
        $this->calls = [];
        $queue = $responses;

        $transport = function (
            string $method,
            string $url,
            array $headers,
            ?string $payload,
            float $timeout,
        ) use (&$queue): array {
            $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $payload];
            if ($queue === []) {
                self::fail('more requests than queued responses');
            }
            $next = array_shift($queue);

            return [
                'status' => $next['status'],
                'body' => (string) json_encode($next['body'] ?? []),
                'headers' => $next['headers'] ?? [],
            ];
        };

        return new SeatLayer('sk_test_abc', 'https://api.seatlayer.io', $maxRetries, 30.0, $transport);
    }

    /** @return array{method:string, url:string, headers:array<string,string>, body:?string} */
    private function call(int $index): array
    {
        self::assertArrayHasKey($index, $this->calls, "No request recorded at index {$index}");

        return $this->calls[$index];
    }

    // ---------- construction ----------

    public function testRejectsPublishableKeyByName(): void
    {
        // The pk_/sk_ mix-up is the most common first-run failure; a 401 three
        // round-trips later teaches nothing.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/publishable key/');
        new SeatLayer('pk_test_abc');
    }

    public function testRejectsNonSecretKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sk_live_ or sk_test_/');
        new SeatLayer('nonsense');
    }

    public function testReportsKeyMode(): void
    {
        self::assertSame('test', (new SeatLayer('sk_test_abc'))->mode);
        self::assertSame('live', (new SeatLayer('sk_live_abc'))->mode);
    }

    // ---------- requests ----------

    public function testSendsBearerAuthAndParsesBody(): void
    {
        $sdk = $this->client([['status' => 200, 'body' => ['meta' => ['key' => 'ev_1']]]]);
        $result = $sdk->events->retrieve('ev_1');

        self::assertSame('ev_1', $result['meta']['key']);
        self::assertSame('Bearer sk_test_abc', $this->call(0)['headers']['Authorization']);
        self::assertSame('https://api.seatlayer.io/v1/events/ev_1', $this->call(0)['url']);
    }

    public function testPercentEncodesPathParameters(): void
    {
        $sdk = $this->client([['status' => 200, 'body' => []]]);
        $sdk->events->retrieve('ev/../admin');
        self::assertSame('https://api.seatlayer.io/v1/events/ev%2F..%2Fadmin', $this->call(0)['url']);
    }

    public function testIdempotencyKeyOnMutationsOnly(): void
    {
        $sdk = $this->client([['status' => 200, 'body' => []], ['status' => 201, 'body' => []]]);
        $sdk->events->list();
        $sdk->events->create('c_1');

        self::assertArrayNotHasKey('Idempotency-Key', $this->call(0)['headers']);
        self::assertMatchesRegularExpression(
            '/^[A-Za-z0-9._:-]{1,128}$/',
            $this->call(1)['headers']['Idempotency-Key'],
        );
    }

    public function testHonoursCallerSuppliedIdempotencyKey(): void
    {
        $sdk = $this->client([['status' => 201, 'body' => []]]);
        $sdk->events->create('c_1', idempotencyKey: 'order-42');
        self::assertSame('order-42', $this->call(0)['headers']['Idempotency-Key']);
    }

    public function testRejectsKeyTheApiWouldReject(): void
    {
        $sdk = $this->client([]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid Idempotency-Key/');
        $sdk->events->create('c_1', idempotencyKey: 'has spaces');
    }

    public function testDropsNullQueryParameters(): void
    {
        $sdk = $this->client([['status' => 200, 'body' => []]]);
        $sdk->charts->list(workspaceId: 'ws_1');
        self::assertSame('https://api.seatlayer.io/v1/charts?workspaceId=ws_1', $this->call(0)['url']);
    }

    // ---------- errors ----------

    public function testModeMismatchIsSelfExplaining(): void
    {
        $sdk = $this->client([['status' => 403, 'body' => ['error' => 'mode_mismatch']]]);
        try {
            $sdk->events->retrieve('ev_1');
            self::fail('expected AuthException');
        } catch (AuthException $error) {
            self::assertTrue($error->isModeMismatch());
        }
    }

    public function testConflictsAreExposedPerSeat(): void
    {
        $sdk = $this->client([[
            'status' => 409,
            'body' => ['error' => 'conflict', 'conflicts' => [['label' => 'A-1', 'status' => 'booked']]],
        ]]);
        try {
            $sdk->inventory->hold('ev_1', ['A-1']);
            self::fail('expected ConflictException');
        } catch (ConflictException $error) {
            self::assertSame([['label' => 'A-1', 'status' => 'booked']], $error->conflicts());
        }
    }

    public function testSoldOutIsABusinessOutcome(): void
    {
        $sdk = $this->client([['status' => 409, 'body' => ['error' => 'conflict', 'reason' => 'sold_out']]]);
        try {
            $sdk->inventory->holdBestAvailable('ev_1', 4);
            self::fail('expected ConflictException');
        } catch (ConflictException $error) {
            self::assertTrue($error->isSoldOut());
        }
    }

    public function testSurfacesRequestIdForSupport(): void
    {
        $sdk = $this->client(
            [['status' => 500, 'body' => ['error' => 'internal'], 'headers' => ['x-request-id' => 'req_9']]],
            maxRetries: 1,
        );
        try {
            $sdk->events->retrieve('ev_1');
            self::fail('expected exception');
        } catch (\SeatLayer\SeatLayerException $error) {
            self::assertSame('req_9', $error->requestId);
        }
    }

    // ---------- retry ----------

    public function testRetries429AndReusesIdempotencyKey(): void
    {
        $sdk = $this->client([
            ['status' => 429, 'body' => ['error' => 'rate_limited'], 'headers' => ['retry-after' => '0']],
            ['status' => 201, 'body' => ['ok' => true]],
        ]);
        $sdk->events->create('c_1');

        self::assertCount(2, $this->calls);
        // Same key on the retry, or the server would create two events.
        self::assertSame(
            $this->call(0)['headers']['Idempotency-Key'],
            $this->call(1)['headers']['Idempotency-Key'],
        );
    }

    public function testDoesNotRetryA4xx(): void
    {
        $sdk = $this->client([['status' => 422, 'body' => ['error' => 'invalid_slug']]]);
        try {
            $sdk->events->create('c_1');
            self::fail('expected ValidationException');
        } catch (ValidationException) {
            self::assertCount(1, $this->calls);
        }
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        $sdk = $this->client([
            ['status' => 429, 'body' => [], 'headers' => ['retry-after' => '0']],
            ['status' => 429, 'body' => [], 'headers' => ['retry-after' => '0']],
        ], maxRetries: 2);

        try {
            $sdk->events->create('c_1');
            self::fail('expected RateLimitException');
        } catch (RateLimitException) {
            self::assertCount(2, $this->calls);
        }
    }

    public function testPrefersRetryAfterHeaderOverJsonField(): void
    {
        $sdk = $this->client([[
            'status' => 429,
            'body' => ['error' => 'rate_limited', 'retryAfterSeconds' => 99],
            'headers' => ['retry-after' => '0'],
        ]], maxRetries: 1);

        try {
            $sdk->events->retrieve('ev_1');
            self::fail('expected RateLimitException');
        } catch (RateLimitException $error) {
            self::assertSame(0.0, $error->retryAfterSeconds);
        }
    }

    // ---------- pagination ----------

    public function testListAllWalksPagesAndStops(): void
    {
        $sdk = $this->client([
            ['status' => 200, 'body' => ['charts' => [['id' => 'c_1'], ['id' => 'c_2']], 'nextCursor' => 'cur_1']],
            ['status' => 200, 'body' => ['charts' => [['id' => 'c_3']]]],
        ]);

        $seen = [];
        foreach ($sdk->charts->listAll(limit: 2) as $chart) {
            $seen[] = $chart['id'];
        }

        self::assertSame(['c_1', 'c_2', 'c_3'], $seen);
        self::assertCount(2, $this->calls);
        // Absent nextCursor terminates — a caller looping cannot spin forever.
        self::assertStringContainsString('cursor=cur_1', $this->call(1)['url']);
    }

    public function testListAllEventsSkipsPerEventCounts(): void
    {
        // Counts cost a server round-trip PER EVENT, which is exactly the cost
        // pagination was added to avoid.
        $sdk = $this->client([['status' => 200, 'body' => ['events' => []]]]);
        iterator_to_array($sdk->events->listAll());
        self::assertStringContainsString('counts=0', $this->call(0)['url']);
    }

    public function testSinglePageKeepsCounts(): void
    {
        $sdk = $this->client([['status' => 200, 'body' => ['events' => []]]]);
        $sdk->events->list(limit: 10);
        self::assertStringNotContainsString('counts=0', $this->call(0)['url']);
    }

    // ---------- sessions ----------

    public function testRefusesToMintWithoutExplicitCapabilities(): void
    {
        $sdk = $this->client([]);
        // The API would default this to all four including event:cancel — the
        // ability to reverse paid bookings should never arrive by omission.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/capabilities is required/');
        $sdk->sessions->createManageSession('ev_1', 'https://box-office.example', []);
    }

    public function testMintsWithGivenCapabilities(): void
    {
        $sdk = $this->client([['status' => 201, 'body' => ['token' => 'mse_x']]]);
        $sdk->sessions->createManageSession('ev_1', 'https://box-office.example', ['event:view']);

        $body = json_decode((string) $this->call(0)['body'], true);
        self::assertSame(['event:view'], $body['capabilities']);
    }

    // ---------- charts / inventory ----------

    public function testChartUpdateSendsExpectedUpdatedAt(): void
    {
        $sdk = $this->client([['status' => 200, 'body' => ['meta' => []]]]);
        $sdk->charts->update('c_1', ['version' => 1], 1234);

        $body = json_decode((string) $this->call(0)['body'], true);
        self::assertSame(1234, $body['expectedUpdatedAt']);
    }

    public function testExtendHoldPostsHoldId(): void
    {
        $sdk = $this->client([['status' => 200, 'body' => ['ok' => true, 'expiresAt' => 123]]]);
        $sdk->inventory->extendHold('ev_1', 'h_9', 600000);

        self::assertSame('https://api.seatlayer.io/v1/events/ev_1/extend', $this->call(0)['url']);
        self::assertSame(
            ['holdId' => 'h_9', 'ttlMs' => 600000],
            json_decode((string) $this->call(0)['body'], true),
        );
    }

    public function testHoldCarriesChannelAuthority(): void
    {
        $sdk = $this->client([['status' => 200, 'body' => ['holdId' => 'h_1']]]);
        $sdk->inventory->hold(
            'ev_1',
            labels: ['A-1'],
            channelIds: ['ch_partner'],
            ignoreChannelRestrictions: false,
            reason: 'partner checkout',
        );

        self::assertSame([
            'labels' => ['A-1'],
            'channelIds' => ['ch_partner'],
            'ignoreChannelRestrictions' => false,
            'reason' => 'partner checkout',
        ], json_decode((string) $this->call(0)['body'], true));
    }

    public function testCreatesOriginBoundBuyerAccessSession(): void
    {
        $sdk = $this->client([['status' => 201, 'body' => ['token' => 'bas_x']]]);
        $sdk->channels->createBuyerAccessSession(
            'ev/1',
            includePublic: false,
            allowedOrigin: 'https://partner.example',
            channelIds: ['ch_1'],
            maxQuantity: 4,
            idempotencyKey: 'partner-order-42',
        );

        self::assertSame(
            'https://api.seatlayer.io/v1/events/ev%2F1/buyer-access-sessions',
            $this->call(0)['url'],
        );
        self::assertSame('partner-order-42', $this->call(0)['headers']['Idempotency-Key']);
        self::assertSame([
            'channelIds' => ['ch_1'],
            'includePublic' => false,
            'allowedOrigin' => 'https://partner.example',
            'maxQuantity' => 4,
        ], json_decode((string) $this->call(0)['body'], true));
    }

    public function testReadsBookingByTrimmedEncodedReference(): void
    {
        $sdk = $this->client([['status' => 200, 'body' => ['bookingRef' => 'order / 42']]]);
        $sdk->inventory->retrieveBooking('ev_1', '  order / 42  ');

        self::assertSame(
            'https://api.seatlayer.io/v1/events/ev_1/bookings/order%20%2F%2042',
            $this->call(0)['url'],
        );
    }

    public function testRejectsBlankBookingReferenceBeforeRequest(): void
    {
        $sdk = $this->client([]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/bookingRef is required/');
        $sdk->inventory->unbook('ev_1', ['A-1'], '   ');
    }

    public function testSpentHoldIsAConflict(): void
    {
        $sdk = $this->client([['status' => 409, 'body' => ['error' => 'cannot_extend', 'reason' => 'expired']]]);
        try {
            $sdk->inventory->extendHold('ev_1', 'h_9');
            self::fail('expected ConflictException');
        } catch (ConflictException $error) {
            self::assertSame('cannot_extend', $error->errorCode);
        }
    }
}
