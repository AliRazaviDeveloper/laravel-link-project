<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Command;

/**
 * A null slug asks the generator for one; anything else is taken as a vanity
 * slug and validated against the same rules.
 */
final readonly class CreateLink
{
    public function __construct(
        public string $accountId,
        public string $destinationUrl,
        public ?string $slug = null,
        public ?string $title = null,
        public ?string $expiresAt = null,
        public ?int $maxClicks = null,
    ) {}
}
