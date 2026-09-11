<?php

declare(strict_types=1);

namespace Shortwave\Application\Analytics\DTO;

use DateTimeImmutable;
use Shortwave\Domain\Analytics\ValueObject\ClickTotals;

final readonly class AccountOverviewView
{
    /**
     * @param  list<array{link_id: string, slug: string, title: string|null, clicks: int}>  $topLinks
     */
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
        public ClickTotals $totals,
        public int $linkCount,
        public int $planLinkAllowance,
        public array $topLinks,
    ) {}

    public function allowanceUsedPercent(): float
    {
        if ($this->planLinkAllowance <= 0) {
            return 0.0;
        }

        return round($this->linkCount / $this->planLinkAllowance * 100, 1);
    }
}
