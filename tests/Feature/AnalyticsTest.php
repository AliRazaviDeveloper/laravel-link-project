<?php

declare(strict_types=1);

use Shortwave\Domain\Account\ValueObject\Plan;

/*
|--------------------------------------------------------------------------
| Analytics
|--------------------------------------------------------------------------
|
| These run against a real MongoDB, because the behaviour under test *is* the
| aggregation: `$dateTrunc` bucketing, `$facet` totals, `$addToSet` cardinality and
| the zero-filling that follows them. A fake repository would only assert that PHP
| can add up numbers it was handed.
|
| Clicks are produced by following real redirects and then draining the pipeline, so
| the fingerprinting, device classification and buffering are all in the path.
|
*/

/**
 * Builds a stats URL with properly encoded parameters.
 *
 * Not cosmetic: an ISO timestamp ends in `+00:00`, and a raw `+` in a query string
 * decodes to a space, so a hand-concatenated URL fails date validation and the test
 * would "pass" on a 422 that has nothing to do with what it claims to check.
 *
 * @param  array<string, string|int>  $params
 */
function statsUrl(string $linkId, array $params = []): string
{
    return '/api/v1/links/'.$linkId.'/stats'.($params === [] ? '' : '?'.http_build_query($params));
}

/**
 * Follows a slug with headers that describe a particular visitor.
 */
function clickAs(object $test, string $slug, string $ua, string $country, ?string $referer = null, string $ip = '203.0.113.9'): void
{
    $headers = [
        'User-Agent' => $ua,
        'CF-IPCountry' => $country,
        'REMOTE_ADDR' => $ip,
    ];

    if ($referer !== null) {
        $headers['Referer'] = $referer;
    }

    $test->get('/'.$slug, $headers);
}

const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3 Mobile/15E148 Safari/604.1';
const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36';
const BOT_UA = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

beforeEach(function (): void {
    $this->account = $this->registerAccount('analytics@example.com', Plan::Pro);
    $this->link = $this->createLink($this->account['token'], ['slug' => 'measured'])->json('data');
});

it('reports totals with bots counted separately', function (): void {
    clickAs($this, 'measured', IPHONE_UA, 'DE', 'https://news.ycombinator.com/x');
    clickAs($this, 'measured', IPHONE_UA, 'DE', 'https://news.ycombinator.com/x');
    clickAs($this, 'measured', DESKTOP_UA, 'FR', 'https://twitter.com/x');
    clickAs($this, 'measured', BOT_UA, 'US');

    $this->drainClickPipeline();

    $totals = $this->asAccount($this->account['token'])
        ->getJson(statsUrl($this->link['id']))
        ->assertOk()
        ->json('data.totals');

    // A crawler sweep must not read as a campaign spike, but it must still be visible.
    expect($totals['clicks'])->toBe(3)
        ->and($totals['bot_clicks'])->toBe(1)
        // Two distinct device profiles behind one address, so two visitors.
        ->and($totals['unique_visitors'])->toBe(2)
        ->and($totals['returning_share_percent'])->toBeGreaterThan(0.0);
});

it('breaks clicks down by country, referrer and device', function (): void {
    clickAs($this, 'measured', IPHONE_UA, 'DE', 'https://news.ycombinator.com/x');
    clickAs($this, 'measured', IPHONE_UA, 'DE', 'https://news.ycombinator.com/x');
    clickAs($this, 'measured', DESKTOP_UA, 'FR', 'https://twitter.com/x');

    $this->drainClickPipeline();

    $breakdowns = $this->asAccount($this->account['token'])
        ->getJson(statsUrl($this->link['id']))
        ->assertOk()
        ->json('data.breakdowns');

    expect(array_column($breakdowns['country'], 'clicks', 'label'))->toBe(['DE' => 2, 'FR' => 1])
        ->and(array_column($breakdowns['referrer'], 'clicks', 'label'))
        // Only the host survives: full referring URLs carry session tokens and search
        // terms in their query strings.
        ->toBe(['news.ycombinator.com' => 2, 'twitter.com' => 1])
        ->and(array_column($breakdowns['device_type'], 'clicks', 'label'))->toBe(['mobile' => 2, 'desktop' => 1])
        ->and($breakdowns['country'][0]['share_percent'])->toBe(66.7);
});

it('records a direct click when there is no referrer', function (): void {
    clickAs($this, 'measured', DESKTOP_UA, 'GB');

    $this->drainClickPipeline();

    $breakdowns = $this->asAccount($this->account['token'])
        ->getJson(statsUrl($this->link['id']))
        ->json('data.breakdowns');

    expect(array_column($breakdowns['referrer'], 'label'))->toBe(['direct']);
});

it('returns a dense series with zero-filled gaps', function (): void {
    clickAs($this, 'measured', DESKTOP_UA, 'GB');

    $this->drainClickPipeline();

    $series = $this->asAccount($this->account['token'])
        ->getJson(statsUrl($this->link['id'], ['granularity' => 'day']))
        ->assertOk()
        ->json('data.series');

    // Every interval in the window is present, so a client can chart the array directly
    // instead of joining two distant points across an apparent gap.
    //
    // 31 buckets for a 30-day window, not 30: the start is floored to midnight, so the
    // partial first day is a bucket of its own. Dropping it would silently discard the
    // clicks that fell in it.
    expect($series)->toHaveCount(31)
        ->and(array_sum(array_column($series, 'clicks')))->toBe(1)
        ->and($series[0]['clicks'])->toBe(0)
        ->and(end($series)['clicks'])->toBe(1);
});

it('accepts each granularity', function (string $granularity): void {
    clickAs($this, 'measured', DESKTOP_UA, 'GB');
    $this->drainClickPipeline();

    $window = $this->asAccount($this->account['token'])
        ->getJson(statsUrl($this->link['id'], [
            'granularity' => $granularity,
            'from' => now()->subDays(6)->toIso8601String(),
        ]))
        ->assertOk()
        ->json('data.window');

    expect($window['granularity'])->toBe($granularity);
})->with(['hour', 'day', 'week', 'month']);

it('refuses hourly buckets over a window too long to chart', function (): void {
    // 30 days is well inside the Pro plan's 180-day retention but far past the 14-day
    // hourly cap, so this isolates the granularity rule. A longer window would be
    // rejected by the retention check first and the test would prove nothing.
    $this->asAccount($this->account['token'])
        ->getJson(statsUrl($this->link['id'], [
            'granularity' => 'hour',
            'from' => now()->subDays(30)->toIso8601String(),
        ]))
        ->assertStatus(422)
        ->assertJsonPath('field', 'granularity');
});

it('refuses a window older than the plan retains', function (): void {
    $free = $this->registerAccount('free-analytics@example.com');
    $link = $this->createLink($free['token'], ['slug' => 'freelink'])->json('data');

    // A free account asking for a year gets an error rather than a misleading zero.
    $this->asAccount($free['token'])
        ->getJson(statsUrl($link['id'], ['from' => now()->subDays(90)->toIso8601String()]))
        ->assertStatus(422)
        ->assertJsonPath('field', 'from')
        ->assertJsonPath('detail', 'Your plan retains 30 days of analytics; the requested window starts earlier.');
});

it('serves a repeated report from cache', function (): void {
    clickAs($this, 'measured', DESKTOP_UA, 'GB');
    $this->drainClickPipeline();

    $url = statsUrl($this->link['id'], ['granularity' => 'day']);

    $first = $this->asAccount($this->account['token'])->getJson($url)->assertOk();
    $second = $this->asAccount($this->account['token'])->getJson($url)->assertOk();

    // Aggregation is the expensive half of this API and dashboards poll, so the whole
    // view is memoised under a key derived from every input that changes the answer.
    expect($first->json('data.meta.cached'))->toBeFalse()
        ->and($second->json('data.meta.cached'))->toBeTrue()
        ->and($second->json('data.totals'))->toBe($first->json('data.totals'));
});

it('keys the cache by window and granularity', function (): void {
    clickAs($this, 'measured', DESKTOP_UA, 'GB');
    $this->drainClickPipeline();

    $this->asAccount($this->account['token'])
        ->getJson(statsUrl($this->link['id'], ['granularity' => 'day']))
        ->assertOk();

    $other = $this->asAccount($this->account['token'])
        ->getJson(statsUrl($this->link['id'], ['granularity' => 'week']))
        ->assertOk();

    // A different granularity is a different question and must not be answered from the
    // previous one's entry.
    expect($other->json('data.meta.cached'))->toBeFalse();
});

it('stores no raw address or User-Agent alongside a click', function (): void {
    clickAs($this, 'measured', IPHONE_UA, 'DE', 'https://news.ycombinator.com/x', '198.51.100.22');

    $this->drainClickPipeline();

    $document = $this->mongo()->getCollection('click_events')->findOne([]);
    $encoded = json_encode($document, JSON_THROW_ON_ERROR);

    // The privacy claim of the whole pipeline, asserted rather than assumed: identifying
    // inputs are reduced to a rotating hash at the edge and never reach storage.
    expect($encoded)->not->toContain('198.51.100')
        ->and($encoded)->not->toContain('iPhone')
        ->and($encoded)->toContain('"visitor"');

    /** @var array<string, mixed> $row */
    $row = (array) $document;
    expect($row['visitor'])->toMatch('/^[a-f0-9]{64}$/');
});

it('summarises the account across both stores', function (): void {
    clickAs($this, 'measured', DESKTOP_UA, 'GB');
    clickAs($this, 'measured', IPHONE_UA, 'DE');

    $this->drainClickPipeline();

    $data = $this->asAccount($this->account['token'])
        ->getJson('/api/v1/overview?days=7')
        ->assertOk()
        ->json('data');

    // Totals from MongoDB, inventory and allowance from Postgres, slugs resolved in one
    // batched lookup — the two stores stitched together in a single response.
    expect($data['totals']['clicks'])->toBe(2)
        ->and($data['inventory']['links'])->toBe(1)
        ->and($data['inventory']['link_allowance'])->toBe(Plan::Pro->linkAllowance())
        ->and($data['top_links'])->toHaveCount(1)
        ->and($data['top_links'][0]['slug'])->toBe('measured')
        ->and($data['top_links'][0]['clicks'])->toBe(2);
});

it('reports an empty window without failing', function (): void {
    $data = $this->asAccount($this->account['token'])
        ->getJson(statsUrl($this->link['id']))
        ->assertOk()
        ->json('data');

    expect($data['totals']['clicks'])->toBe(0)
        ->and($data['totals']['unique_visitors'])->toBe(0)
        ->and($data['breakdowns']['country'])->toBe([])
        // The series is still dense, so a chart of a quiet link renders a flat line
        // rather than nothing at all.
        ->and($data['series'])->toHaveCount(31)
        ->and($data['peak']['clicks'])->toBe(0);
});
