<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Query;

final readonly class ResolveSlug
{
    public function __construct(public string $slug) {}
}
