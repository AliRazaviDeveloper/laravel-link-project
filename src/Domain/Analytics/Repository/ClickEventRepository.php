<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\Repository;

use DateTimeImmutable;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Analytics\Entity\ClickEvent;
use Shortwave\Domain\Analytics\Enum\BreakdownDimension;
use Shortwave\Domain\Analytics\Enum\Granularity;
use Shortwave\Domain\Analytics\ValueObject\BreakdownSlice;
use Shortwave\Domain\Analytics\ValueObject\ClickTotals;
use Shortwave\Domain\Analytics\ValueObject\TimeBucket;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Shared\ValueObject\DateRange;

/**
 * Append-only click log plus the aggregations we report from it.
 *
 * Reads and writes share one port because they share one physical collection and
 * one set of indexes; splitting them would suggest two stores that can diverge.
 */
interface ClickEventRepository
{
    /**
     * @param  list<ClickEvent>  $events
     */
    public function appendMany(array $events): void;

    public function totalsForLink(LinkId $linkId, DateRange $range): ClickTotals;

    public function totalsForAccount(AccountId $accountId, DateRange $range): ClickTotals;

    /**
     * Buckets are dense: every interval in the range is present, zero-filled
     * where no clicks landed, so callers can chart the result directly.
     *
     * @return list<TimeBucket>
     */
    public function timeseriesForLink(LinkId $linkId, DateRange $range, Granularity $granularity): array;

    /**
     * @return list<BreakdownSlice>
     */
    public function breakdownForLink(
        LinkId $linkId,
        DateRange $range,
        BreakdownDimension $dimension,
        int $limit = 10,
    ): array;

    /**
     * Busiest links in the window, as link id => click count, ordered high to low.
     *
     * @return array<string, int>
     */
    public function topLinksForAccount(AccountId $accountId, DateRange $range, int $limit = 5): array;

    public function purgeOlderThan(DateTimeImmutable $threshold): int;
}
