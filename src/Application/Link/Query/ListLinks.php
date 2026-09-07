<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Query;

use Shortwave\Application\Link\DTO\LinkFilter;

final readonly class ListLinks
{
    public function __construct(
        public string $accountId,
        public LinkFilter $filter,
    ) {}
}
