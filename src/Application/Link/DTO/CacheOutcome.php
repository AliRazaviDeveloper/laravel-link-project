<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\DTO;

enum CacheOutcome: string
{
    /** The slug is cached and resolves. */
    case Hit = 'hit';

    /** The slug is cached as known-absent; no need to touch the database. */
    case NegativeHit = 'negative_hit';

    /** Nothing cached; the caller must consult the store. */
    case Miss = 'miss';
}
