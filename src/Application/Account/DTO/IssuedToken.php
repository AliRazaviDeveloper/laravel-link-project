<?php

declare(strict_types=1);

namespace Shortwave\Application\Account\DTO;

use DateTimeImmutable;

/**
 * The plaintext half of a freshly minted token.
 *
 * Only ever crosses the wire once, on the response that created it; the store
 * keeps a hash. Nothing here is logged, which is why the value is not part of any
 * `toArray()` used for telemetry.
 */
final readonly class IssuedToken
{
    public function __construct(
        public string $id,
        public string $plainTextToken,
        public string $name,
        public ?DateTimeImmutable $expiresAt,
    ) {}
}
