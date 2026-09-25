<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Support;

use DateTimeImmutable;
use DateTimeZone;
use Shortwave\Domain\Shared\Contract\Clock;

final readonly class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
