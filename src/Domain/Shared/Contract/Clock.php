<?php

declare(strict_types=1);

namespace Shortwave\Domain\Shared\Contract;

use DateTimeImmutable;

/**
 * Time as a dependency, so expiry and bucketing logic stays testable.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
