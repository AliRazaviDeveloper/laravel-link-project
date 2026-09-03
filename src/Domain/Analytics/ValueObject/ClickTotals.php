<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\ValueObject;

final readonly class ClickTotals
{
    public function __construct(
        public int $clicks,
        public int $uniqueVisitors,
        public int $botClicks,
    ) {}

    public static function zero(): self
    {
        return new self(0, 0, 0);
    }

    /**
     * Repeat traffic as a share of all human clicks. A campaign with a high
     * figure is being re-opened rather than newly discovered.
     */
    public function returningShare(): float
    {
        if ($this->clicks <= 0 || $this->uniqueVisitors <= 0) {
            return 0.0;
        }

        return round(($this->clicks - $this->uniqueVisitors) / $this->clicks * 100, 1);
    }
}
