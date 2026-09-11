<?php

declare(strict_types=1);

namespace Shortwave\Application\Analytics\DTO;

use DateTimeImmutable;
use Shortwave\Domain\Analytics\Enum\Granularity;
use Shortwave\Domain\Analytics\ValueObject\BreakdownSlice;
use Shortwave\Domain\Analytics\ValueObject\ClickTotals;
use Shortwave\Domain\Analytics\ValueObject\TimeBucket;

final readonly class LinkStatsView
{
    /**
     * @param  list<TimeBucket>  $series
     * @param  array<string, list<BreakdownSlice>>  $breakdowns  keyed by dimension
     */
    public function __construct(
        public string $linkId,
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
        public Granularity $granularity,
        public ClickTotals $totals,
        public array $series,
        public array $breakdowns,
        public bool $fromCache = false,
    ) {}

    public function withCacheFlag(bool $fromCache): self
    {
        return new self(
            $this->linkId,
            $this->from,
            $this->until,
            $this->granularity,
            $this->totals,
            $this->series,
            $this->breakdowns,
            $fromCache,
        );
    }

    /**
     * The busiest bucket in the window — the "peak" figure clients usually want
     * next to a total.
     */
    public function peak(): ?TimeBucket
    {
        if ($this->series === []) {
            return null;
        }

        return array_reduce(
            $this->series,
            static fn (?TimeBucket $best, TimeBucket $bucket): TimeBucket => $best === null || $bucket->clicks > $best->clicks
                ? $bucket
                : $best,
        );
    }
}
