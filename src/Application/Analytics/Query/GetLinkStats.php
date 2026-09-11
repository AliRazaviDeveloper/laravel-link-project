<?php

declare(strict_types=1);

namespace Shortwave\Application\Analytics\Query;

use Shortwave\Domain\Analytics\Enum\BreakdownDimension;
use Shortwave\Domain\Analytics\Enum\Granularity;

final readonly class GetLinkStats
{
    /**
     * @param  list<BreakdownDimension>  $dimensions
     */
    public function __construct(
        public string $accountId,
        public string $linkId,
        public ?string $from,
        public ?string $until,
        public Granularity $granularity = Granularity::Day,
        public array $dimensions = [],
        public int $breakdownLimit = 10,
    ) {}
}
