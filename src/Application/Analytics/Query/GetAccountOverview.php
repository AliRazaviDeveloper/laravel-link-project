<?php

declare(strict_types=1);

namespace Shortwave\Application\Analytics\Query;

final readonly class GetAccountOverview
{
    public function __construct(
        public string $accountId,
        public int $days = 30,
    ) {}
}
