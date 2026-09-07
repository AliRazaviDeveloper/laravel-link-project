<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Query;

final readonly class ShowLink
{
    public function __construct(
        public string $accountId,
        public string $linkId,
    ) {}
}
