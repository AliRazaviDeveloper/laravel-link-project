<?php

declare(strict_types=1);

namespace Shortwave\Domain\Account\ValueObject;

/**
 * Plans carry their own limits so quota checks never turn into a config lookup
 * scattered across handlers.
 */
enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Scale = 'scale';

    public function linkAllowance(): int
    {
        return match ($this) {
            self::Free => 50,
            self::Pro => 5_000,
            self::Scale => 100_000,
        };
    }

    /**
     * Requests per minute for the authenticated API surface.
     */
    public function requestsPerMinute(): int
    {
        return match ($this) {
            self::Free => 60,
            self::Pro => 600,
            self::Scale => 3_000,
        };
    }

    /**
     * How far back analytics queries may reach, in days.
     */
    public function analyticsRetentionDays(): int
    {
        return match ($this) {
            self::Free => 30,
            self::Pro => 180,
            self::Scale => 365,
        };
    }

    public function allowsBulkImport(): bool
    {
        return $this !== self::Free;
    }
}
