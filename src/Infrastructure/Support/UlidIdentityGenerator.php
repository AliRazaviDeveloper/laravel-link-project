<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Support;

use Illuminate\Support\Str;
use Shortwave\Application\Shared\Contract\IdentityGenerator;

final readonly class UlidIdentityGenerator implements IdentityGenerator
{
    public function next(): string
    {
        return strtoupper((string) Str::ulid());
    }
}
