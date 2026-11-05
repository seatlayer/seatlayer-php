<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use SeatLayer\HttpClient;

/**
 * Event series: many dated events planned from one schedule.
 *
 * A schedule is `rule` ('none', 'daily', 'weekly', 'biweekly' or 'dates') and `times`
 * (`HH:MM` start times), with `from`, `until` or `count`, `skip`, and `dates` as the API
 * describes them.
 */
final class EventSeries
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Plan an event series from a schedule (`dryRun`), or make it as a job. Every date
     * carries the series name as its name.
     *
     * Give `name` and `chartKey`, or `fromEventKey`: an event you already set up becomes
     * the first date under its own key, and every other date is a copy of it (floor plan,
     * prices, checkout, poster and its own seat blocks, none of its orders, holds or sales).
     * The series takes the event's name, zone and floor plan.
     *
     * @param array{name:string,chartKey:string,schedule:array{rule:'none'|'daily'|'weekly'|'biweekly'|'dates',times:list<string>,from?:string,until?:string,count?:int,skip?:list<string>,dates?:list<string>},timezone?:string,venue?:string,durationMinutes?:int,dryRun?:bool}|array{fromEventKey:string,schedule:array{rule:'none'|'daily'|'weekly'|'biweekly'|'dates',times:list<string>,from?:string,until?:string,count?:int,skip?:list<string>,dates?:list<string>},name?:string,timezone?:string,dryRun?:bool} $params
     * @return array<string, mixed>
     */
    public function createEventSeries(array $params, ?string $idempotencyKey = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay('/v1/event-series', $params, $idempotencyKey);
    }

    /**
     * List event series, one page at a time.
     *
     * @return array<string, mixed>
     */
    public function listEventSeries(?int $limit = null, ?string $cursor = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/event-series', array_filter([
            'limit' => $limit,
            'cursor' => $cursor,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Retrieve an event series, its dates and its job.
     *
     * @return array<string, mixed>
     */
    public function retrieveEventSeries(string $eventKey): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/event-series/' . HttpClient::encode($eventKey));
    }

    /**
     * Add dates to an event series, copied from its first date: a `schedule`, exact `dates`
     * (`YYYY-MM-DDTHH:MM` in the series' zone), or `['retryFailed' => true]` to make again every
     * date that failed to be made (`422 nothing_to_retry` when none did, `409 series_busy` while
     * another change runs).
     *
     * @param array{schedule:array{rule:'none'|'daily'|'weekly'|'biweekly'|'dates',times:list<string>,from?:string,until?:string,count?:int,skip?:list<string>,dates?:list<string>}}|array{dates:list<string>}|array{retryFailed:true} $params
     * @return array<string, mixed>
     */
    public function addEventSeriesDates(string $eventKey, array $params, ?string $idempotencyKey = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay(
            '/v1/event-series/' . HttpClient::encode($eventKey) . '/dates',
            $params,
            $idempotencyKey,
        );
    }

    /**
     * Change the name, prices or sales of every upcoming date.
     *
     * @param array{name?:string,prices?:array{categories:list<array{key:string,price?:int|float|null,tiers?:list<array{id:string,price:int|float}>}>},sales?:'on'|'off'} $params
     * @return array<string, mixed>
     */
    public function updateEventSeries(string $eventKey, array $params, ?string $idempotencyKey = null): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->mutationWithHeaderReplay(
            'PATCH',
            '/v1/event-series/' . HttpClient::encode($eventKey),
            $params,
            $idempotencyKey,
        );
    }
}
