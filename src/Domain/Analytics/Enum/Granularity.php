<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\Enum;

use DateTimeImmutable;

enum Granularity: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    /**
     * Snaps a timestamp down to the start of its bucket. Used by both the
     * aggregation pipeline and the gap-filling that follows it, so a bucket key
     * is computed exactly one way.
     */
    public function floor(DateTimeImmutable $moment): DateTimeImmutable
    {
        return match ($this) {
            self::Hour => $moment->setTime((int) $moment->format('G'), 0),
            self::Day => $moment->setTime(0, 0),
            self::Week => $moment->modify('monday this week')->setTime(0, 0),
            self::Month => $moment->setDate(
                (int) $moment->format('Y'),
                (int) $moment->format('n'),
                1,
            )->setTime(0, 0),
        };
    }

    public function advance(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->modify('+1 '.$this->value);
    }

    /**
     * MongoDB `$dateTrunc` unit name. Identical to our own naming today; kept as
     * a mapping so a future bucket size (quarter, minute) cannot silently leak a
     * PHP-only name into a pipeline.
     */
    public function mongoUnit(): string
    {
        return match ($this) {
            self::Hour => 'hour',
            self::Day => 'day',
            self::Week => 'week',
            self::Month => 'month',
        };
    }

    /**
     * Guards against a caller asking for hourly buckets over a year, which would
     * return nearly nine thousand rows.
     */
    public function maxSpanInSeconds(): int
    {
        return match ($this) {
            self::Hour => 14 * 86_400,
            self::Day => 400 * 86_400,
            self::Week, self::Month => 5 * 365 * 86_400,
        };
    }
}
