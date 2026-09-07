<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Command;

final readonly class RestoreLink
{
    public function __construct(
        public string $accountId,
        public string $linkId,
    ) {}
}
