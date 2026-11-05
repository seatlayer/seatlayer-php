<?php

declare(strict_types=1);

namespace SeatLayer\Tests;

use PHPUnit\Framework\TestCase;
use SeatLayer\ConflictException;
use SeatLayer\RateLimitException;
use SeatLayer\SeatLayer;

/** Holders, orders, event sales settings and event series: routes, bodies and replay classes. */
final class SalesOperationsTest extends TestCase
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

    /**
     * Each recorded request as [verb, path with query, raw JSON body, sent an Idempotency-Key].
     *
     * @return array<int, array{0:string,1:string,2:?string,3:bool}>
     */
    private function recorded(): array
    {
        return array_map(static fn (array $call): array => [
            $call['method'],
            substr($call['url'], strlen('https://api.seatlayer.io')),
            $call['body'],
            array_key_exists('Idempotency-Key', $call['headers']),
        ], $this->calls);
    }

    public function testHolderOperations(): void
    {
        $holders = $this->client()->holders;

        $holders->listCustomers('ada@example.com', 'H/1', 'c/1', 10);
        $holders->retrieveHolder('H/1');
        $holders->updateHolder('H/1', ['email' => 'ada@example.com', 'phone' => null], 'holder-update-1');
        $holders->revokeHolderSessions('H/1', 'sess_1');
        $holders->revokeHolderSessions('H/1');
        $holders->listHolderSeasons('H/1');
        $holders->listHolderTickets('H/1', 'sea/a', 1798761600000, 1799366400000, 'c/2', 20);
        $holders->retrieveHolderTicket('H/1', 'tkt/1');
        $holders->createHolderTransfer('H/1', ['tkt_1', 'tkt_2'], 'friend@example.com', 'transfer-1');
        $holders->listHolderTransfers('H/1');
        $holders->cancelHolderTransfer('H/1', 'xfer/1');
        $holders->acceptHolderTransfer('H/1', 'xfer/1');

        self::assertSame([
            ['GET', '/v1/customers?email=ada%40example.com&holderRef=H%2F1&cursor=c%2F1&limit=10', null, false],
            ['GET', '/v1/holders/H%2F1', null, false],
            ['PATCH', '/v1/holders/H%2F1', '{"email":"ada@example.com","phone":null}', true],
            ['POST', '/v1/holders/H%2F1/sessions/revoke', '{"sessionId":"sess_1"}', false],
            ['POST', '/v1/holders/H%2F1/sessions/revoke', '{}', false],
            ['GET', '/v1/holders/H%2F1/seasons', null, false],
            [
                'GET',
                '/v1/holders/H%2F1/tickets?seasonKey=sea%2Fa&from=1798761600000&to=1799366400000&cursor=c%2F2&limit=20',
                null,
                false,
            ],
            ['GET', '/v1/holders/H%2F1/tickets/tkt%2F1', null, false],
            [
                'POST',
                '/v1/holders/H%2F1/transfers',
                '{"ticketIds":["tkt_1","tkt_2"],"toEmail":"friend@example.com"}',
                true,
            ],
            ['GET', '/v1/holders/H%2F1/transfers', null, false],
            ['POST', '/v1/holders/H%2F1/transfers/xfer%2F1/cancel', null, false],
            ['POST', '/v1/holders/H%2F1/transfers/xfer%2F1/accept', null, false],
        ], $this->recorded());
        self::assertSame('holder-update-1', $this->calls[2]['headers']['Idempotency-Key']);
        self::assertSame('transfer-1', $this->calls[8]['headers']['Idempotency-Key']);
    }

    public function testOrderOperations(): void
    {
        $orders = $this->client()->orders;

        $orders->listOrders('ev/1', 'sea/a', 'paid', 1, 2, 'c/1', 10);
        $orders->retrieveOrder('ord/1');
        $orders->listOrderTickets('ord/1');
        $orders->listEventOrders('ev/1', 'ada smith', 'paid', 'card', 1, 2, 'c/1', 25);
        $orders->retrieveEventOrder('ev/1', 'ord/1');
        $orders->refundEventOrder('ev/1', 'ord/1', 500, 'refund-1');
        $orders->refundEventOrder('ev/1', 'ord/1');
        $orders->cancelEventOrder(
            'ev/1',
            'ord/1',
            ['reason' => 'lost_dispute', 'usedTicketsAcknowledged' => true],
            'cancel-1',
        );
        $orders->resendEventOrderTickets('ev/1', 'ord/1', 'ada@example.com');
        $orders->resendEventOrderTickets('ev/1', 'ord/1');

        self::assertSame([
            ['GET', '/v1/orders?eventKey=ev%2F1&seasonKey=sea%2Fa&status=paid&from=1&to=2&cursor=c%2F1&limit=10', null, false],
            ['GET', '/v1/orders/ord%2F1', null, false],
            ['GET', '/v1/orders/ord%2F1/tickets', null, false],
            [
                'GET',
                '/v1/events/ev%2F1/orders?q=ada+smith&status=paid&paidBy=card&from=1&to=2&cursor=c%2F1&limit=25',
                null,
                false,
            ],
            ['GET', '/v1/events/ev%2F1/orders/ord%2F1', null, false],
            ['POST', '/v1/events/ev%2F1/orders/ord%2F1/refund', '{"amountMinor":500}', true],
            ['POST', '/v1/events/ev%2F1/orders/ord%2F1/refund', '{}', true],
            [
                'POST',
                '/v1/events/ev%2F1/orders/ord%2F1/cancel',
                '{"reason":"lost_dispute","usedTicketsAcknowledged":true}',
                true,
            ],
            ['POST', '/v1/events/ev%2F1/orders/ord%2F1/resend', '{"email":"ada@example.com"}', false],
            ['POST', '/v1/events/ev%2F1/orders/ord%2F1/resend', '{}', false],
        ], $this->recorded());
        self::assertSame('refund-1', $this->calls[5]['headers']['Idempotency-Key']);
        self::assertSame('cancel-1', $this->calls[7]['headers']['Idempotency-Key']);
    }

    public function testEventOrdersCsvReturnsTheRawText(): void
    {
        $csv = "order,buyer\nSL-1,\"Smith, Ada\"\n";
        $orders = $this->client(200, $csv)->orders;

        self::assertSame($csv, $orders->exportEventOrdersCsv('ev/1', 'ada', 'paid', 'card', 1, 2));
        self::assertSame([
            ['GET', '/v1/events/ev%2F1/orders.csv?q=ada&status=paid&paidBy=card&from=1&to=2', null, false],
        ], $this->recorded());
        self::assertStringStartsWith('text/csv', $this->calls[0]['headers']['Accept']);
    }

    public function testCsvExportErrorsStillMapToTypedExceptions(): void
    {
        $orders = $this->client(409, '{"code":"no_orders","message":"This event uses its own checkout."}')->orders;

        try {
            $orders->exportEventOrdersCsv('ev/1');
            self::fail('expected ConflictException');
        } catch (ConflictException $error) {
            self::assertSame('no_orders', $error->errorCode);
        }
    }

    public function testEventSalesOperations(): void
    {
        $events = $this->client()->events;

        $events->retrieveEventBuyerCheckout('ev/1');
        $events->replaceEventBuyerCheckout('ev/1', 'seatlayer', 4);
        $events->retrieveEventPaymentGateway('ev/1', 'EUR');
        $events->replaceEventPaymentGateway('ev/1', ['connectionId' => 'conn_1']);
        $events->replaceEventPaymentGateway('ev/1');
        $events->retrieveEventSalesReadiness('ev/1');
        $events->preflightEventSales('ev/1', 'ord_test_1');
        $events->transitionEventSales('ev/1', 'live', 5, 'Doors open');
        $events->retrieveEventBookingFee('ev/1');
        $events->replaceEventBookingFee('ev/1', 150);
        $events->replaceEventBookingFee('ev/1', null);
        $events->listEventDiscountCodes('ev/1');
        $events->createEventDiscountCode(
            'ev/1',
            ['code' => 'EARLY', 'kind' => 'percent', 'percent' => 10, 'maxUses' => null],
        );
        $events->updateEventDiscountCode('ev/1', 'dc/1', ['state' => 'paused']);
        $events->endEventDiscountCode('ev/1', 'dc/1');

        self::assertSame([
            ['GET', '/v1/events/ev%2F1/buyer-checkout', null, false],
            ['PUT', '/v1/events/ev%2F1/buyer-checkout', '{"buyerCheckout":"seatlayer","expectedRevision":4}', false],
            ['GET', '/v1/events/ev%2F1/payment-gateway?currency=EUR', null, false],
            ['PUT', '/v1/events/ev%2F1/payment-gateway', '{"connectionId":"conn_1"}', false],
            ['PUT', '/v1/events/ev%2F1/payment-gateway', '{}', false],
            ['GET', '/v1/events/ev%2F1/sales/readiness', null, false],
            ['POST', '/v1/events/ev%2F1/sales/preflight', '{"orderId":"ord_test_1"}', false],
            [
                'POST',
                '/v1/events/ev%2F1/sales/transition',
                '{"to":"live","expectedRevision":5,"reason":"Doors open"}',
                false,
            ],
            ['GET', '/v1/events/ev%2F1/booking-fee', null, false],
            ['PUT', '/v1/events/ev%2F1/booking-fee', '{"bookingFeeMinor":150}', false],
            ['PUT', '/v1/events/ev%2F1/booking-fee', '{"bookingFeeMinor":null}', false],
            ['GET', '/v1/events/ev%2F1/discount-codes', null, false],
            [
                'POST',
                '/v1/events/ev%2F1/discount-codes',
                '{"code":"EARLY","kind":"percent","percent":10,"maxUses":null}',
                false,
            ],
            ['PATCH', '/v1/events/ev%2F1/discount-codes/dc%2F1', '{"state":"paused"}', false],
            ['POST', '/v1/events/ev%2F1/discount-codes/dc%2F1/end', null, false],
        ], $this->recorded());
    }

    public function testDeleteEventDiscountCodeAcceptsAnEmpty204(): void
    {
        $events = $this->client(204, '')->events;

        $events->deleteEventDiscountCode('ev/1', 'dc/1');

        self::assertSame([
            ['DELETE', '/v1/events/ev%2F1/discount-codes/dc%2F1', null, false],
        ], $this->recorded());
    }

    public function testEventSeriesOperations(): void
    {
        $series = $this->client(202)->eventSeries;
        $schedule = ['rule' => 'weekly', 'times' => ['19:30'], 'from' => '2026-11-03', 'count' => 6];

        $series->createEventSeries(
            ['name' => 'Hamlet', 'chartKey' => 'ch_1', 'schedule' => $schedule, 'timezone' => 'UTC', 'dryRun' => true],
            'series-1',
        );
        $series->listEventSeries(20, 'c/1');
        $series->retrieveEventSeries('ser/1');
        $series->addEventSeriesDates('ser/1', ['dates' => ['2026-12-01T19:30']], 'dates-1');
        $series->updateEventSeries(
            'ser/1',
            ['sales' => 'off', 'prices' => ['categories' => [['key' => 'stalls', 'price' => 25]]]],
            'series-update-1',
        );

        self::assertSame([
            [
                'POST',
                '/v1/event-series',
                '{"name":"Hamlet","chartKey":"ch_1","schedule":{"rule":"weekly","times":["19:30"],'
                    . '"from":"2026-11-03","count":6},"timezone":"UTC","dryRun":true}',
                true,
            ],
            ['GET', '/v1/event-series?limit=20&cursor=c%2F1', null, false],
            ['GET', '/v1/event-series/ser%2F1', null, false],
            ['POST', '/v1/event-series/ser%2F1/dates', '{"dates":["2026-12-01T19:30"]}', true],
            [
                'PATCH',
                '/v1/event-series/ser%2F1',
                '{"sales":"off","prices":{"categories":[{"key":"stalls","price":25}]}}',
                true,
            ],
        ], $this->recorded());
        self::assertSame('series-1', $this->calls[0]['headers']['Idempotency-Key']);
        self::assertSame('dates-1', $this->calls[3]['headers']['Idempotency-Key']);
        self::assertSame('series-update-1', $this->calls[4]['headers']['Idempotency-Key']);
    }

    public function testReplayMutationsRetryWithOneKeyAndUnsupportedOnesAreSentOnce(): void
    {
        $sdk = $this->client(429, '{"error":"rate_limited"}', ['retry-after' => '0']);

        try {
            $sdk->orders->refundEventOrder('ev/1', 'ord/1', 500);
            self::fail('expected RateLimitException');
        } catch (RateLimitException) {
            self::assertCount(3, $this->calls);
            $keys = array_map(static fn (array $call): string => $call['headers']['Idempotency-Key'], $this->calls);
            self::assertCount(1, array_unique($keys));
        }

        try {
            $sdk->orders->resendEventOrderTickets('ev/1', 'ord/1');
            self::fail('expected RateLimitException');
        } catch (RateLimitException) {
            self::assertCount(4, $this->calls);
            self::assertArrayNotHasKey('Idempotency-Key', $this->calls[3]['headers']);
        }

        try {
            $sdk->events->createEventDiscountCode('ev/1', ['code' => 'EARLY', 'kind' => 'amount', 'amountMinor' => 500]);
            self::fail('expected RateLimitException');
        } catch (RateLimitException) {
            self::assertCount(5, $this->calls);
            self::assertArrayNotHasKey('Idempotency-Key', $this->calls[4]['headers']);
        }
    }
}
