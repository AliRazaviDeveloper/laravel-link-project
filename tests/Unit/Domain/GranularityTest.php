<?php

declare(strict_types=1);

use Shortwave\Domain\Analytics\Enum\Granularity;

/*
 * Bucket maths is shared between the aggregation pipeline and the zero-filling that
 * follows it. If the two disagreed by a second, every chart would show gaps next to
 * populated buckets, so the floor is pinned down here.
 */

it('floors to the start of the hour', function (): void {
    expect(Granularity::Hour->floor(at('2026-03-01 13:47:31')))
        ->toEqual(at('2026-03-01 13:00:00'));
});

it('floors to midnight', function (): void {
    expect(Granularity::Day->floor(at('2026-03-01 13:47:31')))
        ->toEqual(at('2026-03-01 00:00:00'));
});

it('floors to Monday, matching the pipeline startOfWeek', function (): void {
    // 2026-03-01 is a Sunday, so the week it belongs to starts on 2026-02-23.
    expect(Granularity::Week->floor(at('2026-03-01 13:47:31')))
        ->toEqual(at('2026-02-23 00:00:00'))
        ->and(Granularity::Week->floor(at('2026-02-23 00:00:00')))
        ->toEqual(at('2026-02-23 00:00:00'));
});

it('floors to the first of the month', function (): void {
    expect(Granularity::Month->floor(at('2026-03-31 23:59:59')))
        ->toEqual(at('2026-03-01 00:00:00'));
});

it('advances by exactly one bucket', function (Granularity $granularity, string $from, string $expected): void {
    expect($granularity->advance(at($from)))->toEqual(at($expected));
})->with([
    [Granularity::Hour, '2026-03-01 13:00:00', '2026-03-01 14:00:00'],
    [Granularity::Day, '2026-03-01 00:00:00', '2026-03-02 00:00:00'],
    [Granularity::Week, '2026-02-23 00:00:00', '2026-03-02 00:00:00'],
    // Month arithmetic must not overflow into the next-but-one month.
    [Granularity::Month, '2026-01-31 00:00:00', '2026-03-03 00:00:00'],
]);

it('caps how long a window may be for each bucket size', function (): void {
    // Hourly buckets over a year would return nearly nine thousand rows, so the
    // handler refuses rather than building it.
    expect(Granularity::Hour->maxSpanInSeconds())->toBeLessThan(Granularity::Day->maxSpanInSeconds())
        ->and(Granularity::Day->maxSpanInSeconds())->toBeLessThan(Granularity::Month->maxSpanInSeconds());
});

it('maps every case to a Mongo date unit', function (Granularity $granularity): void {
    expect($granularity->mongoUnit())->toBeIn(['hour', 'day', 'week', 'month']);
})->with(Granularity::cases());
