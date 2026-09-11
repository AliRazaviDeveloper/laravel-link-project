<?php

declare(strict_types=1);

namespace Shortwave\Application\Analytics\Handler;

use DateTimeImmutable;
use Shortwave\Application\Analytics\DTO\LinkStatsView;
use Shortwave\Application\Analytics\Query\GetLinkStats;
use Shortwave\Application\Analytics\Service\StatsCache;
use Shortwave\Application\Link\Handler\LinkGuard;
use Shortwave\Application\Shared\Support\Timestamp;
use Shortwave\Domain\Account\Exception\AccountNotFound;
use Shortwave\Domain\Account\Repository\AccountRepository;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Analytics\Enum\BreakdownDimension;
use Shortwave\Domain\Analytics\Enum\Granularity;
use Shortwave\Domain\Analytics\Repository\ClickEventRepository;
use Shortwave\Domain\Analytics\ValueObject\BreakdownSlice;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Shared\Contract\Clock;
use Shortwave\Domain\Shared\Exception\InvariantViolation;
use Shortwave\Domain\Shared\ValueObject\DateRange;

/**
 * Reports on a single link.
 *
 * Aggregation is the expensive half of this API: a breakdown over a busy link
 * scans a lot of documents, and dashboards poll. So the whole view is memoised
 * under a key derived from every input that can change the answer, and the TTL is
 * tied to bucket size — an hourly view can go stale for a minute, a monthly one
 * for much longer.
 */
final readonly class GetLinkStatsHandler
{
    /**
     * Requested but unnamed dimensions default to the three that answer most
     * questions without a second request.
     */
    private const array DEFAULT_DIMENSIONS = [
        BreakdownDimension::Country,
        BreakdownDimension::Referrer,
        BreakdownDimension::DeviceType,
    ];

    public function __construct(
        private LinkGuard $guard,
        private AccountRepository $accounts,
        private ClickEventRepository $clicks,
        private StatsCache $cache,
        private Clock $clock,
    ) {}

    public function handle(GetLinkStats $query): LinkStatsView
    {
        $accountId = AccountId::fromString($query->accountId);
        $account = $this->accounts->findById($accountId);

        if ($account === null) {
            throw AccountNotFound::withId($accountId);
        }

        // Ownership is proven before anything is read from the analytics store,
        // which has no notion of who owns what.
        $link = $this->guard->ownedBy($query->accountId, $query->linkId);

        $now = $this->clock->now();
        $range = $this->resolveRange($query, $now);

        // The plan's retention window is a hard floor, not a display hint: a free
        // account asking for a year gets an error rather than a misleading zero.
        $this->assertWithinRetention($range, $account->plan()->analyticsRetentionDays(), $now);
        $this->assertSensibleGranularity($query, $range);

        $dimensions = $query->dimensions === [] ? self::DEFAULT_DIMENSIONS : $query->dimensions;

        return $this->cache->remember(
            $this->cacheKey($link->id(), $range, $query, $dimensions),
            $this->ttlFor($query),
            fn (): LinkStatsView => $this->compute($link->id(), $range, $query, $dimensions),
        );
    }

    /**
     * @param  list<BreakdownDimension>  $dimensions
     */
    private function compute(
        LinkId $linkId,
        DateRange $range,
        GetLinkStats $query,
        array $dimensions,
    ): LinkStatsView {
        /** @var array<string, list<BreakdownSlice>> $breakdowns */
        $breakdowns = [];

        foreach ($dimensions as $dimension) {
            $breakdowns[$dimension->value] = $this->clicks->breakdownForLink(
                $linkId,
                $range,
                $dimension,
                $query->breakdownLimit,
            );
        }

        return new LinkStatsView(
            linkId: $linkId->value,
            from: $range->from,
            until: $range->until,
            granularity: $query->granularity,
            totals: $this->clicks->totalsForLink($linkId, $range),
            series: $this->clicks->timeseriesForLink($linkId, $range, $query->granularity),
            breakdowns: $breakdowns,
        );
    }

    private function resolveRange(GetLinkStats $query, DateTimeImmutable $now): DateRange
    {
        $until = Timestamp::parseOptional($query->until, 'until') ?? $now;
        $from = Timestamp::parseOptional($query->from, 'from')
            ?? $until->modify('-30 days');

        return DateRange::between($from, $until);
    }

    private function assertWithinRetention(DateRange $range, int $retentionDays, DateTimeImmutable $now): void
    {
        $earliest = $now->modify(sprintf('-%d days', $retentionDays));

        if ($range->from < $earliest) {
            throw InvariantViolation::for('from', sprintf(
                'Your plan retains %d days of analytics; the requested window starts earlier.',
                $retentionDays,
            ));
        }
    }

    private function assertSensibleGranularity(GetLinkStats $query, DateRange $range): void
    {
        if ($range->durationInSeconds() > $query->granularity->maxSpanInSeconds()) {
            throw InvariantViolation::for('granularity', sprintf(
                'A %s breakdown cannot span that long a window; use a coarser granularity.',
                $query->granularity->value,
            ));
        }
    }

    /**
     * @param  list<BreakdownDimension>  $dimensions
     */
    private function cacheKey(
        LinkId $linkId,
        DateRange $range,
        GetLinkStats $query,
        array $dimensions,
    ): string {
        $dimensionKey = implode(',', array_map(
            static fn (BreakdownDimension $dimension): string => $dimension->value,
            $dimensions,
        ));

        // Bucket boundaries rather than raw timestamps, so the thousands of
        // "last 30 days" requests that differ only by the current second all land
        // on the same entry.
        return implode(':', [
            'link', $linkId->value,
            'g', $query->granularity->value,
            'f', $query->granularity->floor($range->from)->getTimestamp(),
            'u', $query->granularity->floor($range->until)->getTimestamp(),
            'd', $dimensionKey,
            'n', (string) $query->breakdownLimit,
        ]);
    }

    private function ttlFor(GetLinkStats $query): int
    {
        return match ($query->granularity) {
            Granularity::Hour => 60,
            Granularity::Day => 300,
            Granularity::Week => 900,
            Granularity::Month => 1_800,
        };
    }
}
