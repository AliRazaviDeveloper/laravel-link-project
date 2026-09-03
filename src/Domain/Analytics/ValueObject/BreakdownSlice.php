<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\ValueObject;

final readonly class BreakdownSlice
{
    public function __construct(
        public string $label,
        public int $clicks,
    ) {}

    /**
     * Share of a total, as a percentage rounded to one decimal place.
     */
    public function shareOf(int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round($this->clicks / $total * 100, 1);
    }
}
