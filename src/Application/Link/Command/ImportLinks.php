<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Command;

final readonly class ImportLinks
{
    public const int MAX_BATCH = 200;

    /**
     * @param  list<CreateLink>  $links
     */
    public function __construct(
        public string $accountId,
        public array $links,
        public bool $stopOnFirstError = false,
    ) {}
}
