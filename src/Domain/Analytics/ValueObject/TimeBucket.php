<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\ValueObject;

use DateTimeImmutable;

final readonly class TimeBucket
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public int $clicks,
        public int $uniqueVisitors,
    ) {}

    public static function empty(DateTimeImmutable $startsAt): self
    {
        return new self($startsAt, 0, 0);
    }
}
