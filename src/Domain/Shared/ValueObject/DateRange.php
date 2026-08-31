<?php

declare(strict_types=1);

namespace Shortwave\Domain\Shared\ValueObject;

use DateTimeImmutable;
use Shortwave\Domain\Shared\Exception\InvariantViolation;

/**
 * Half-open interval [from, until) — the closing bound is exclusive so adjacent
 * ranges can be queried without double counting the boundary second.
 */
final readonly class DateRange
{
    private const int MAX_SPAN_DAYS = 400;

    private function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
    ) {}

    public static function between(DateTimeImmutable $from, DateTimeImmutable $until): self
    {
        if ($from >= $until) {
            throw InvariantViolation::for('from', 'The range start must fall before its end.');
        }

        if ($from->diff($until)->days > self::MAX_SPAN_DAYS) {
            throw InvariantViolation::for(
                'until',
                sprintf('A range may not span more than %d days.', self::MAX_SPAN_DAYS),
            );
        }

        return new self($from, $until);
    }

    public static function lastDays(int $days, DateTimeImmutable $now): self
    {
        if ($days < 1) {
            throw InvariantViolation::for('days', 'The window must cover at least one day.');
        }

        return self::between($now->modify(sprintf('-%d days', $days)), $now);
    }

    public function durationInSeconds(): int
    {
        return $this->until->getTimestamp() - $this->from->getTimestamp();
    }

    public function contains(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->from && $moment < $this->until;
    }
}
