<?php

declare(strict_types=1);

namespace Shortwave\Application\Shared\Contract;

interface IdentityGenerator
{
    /**
     * A fresh ULID string, monotonic within the process.
     */
    public function next(): string;
}
