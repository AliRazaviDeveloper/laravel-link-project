<?php

declare(strict_types=1);

namespace Shortwave\Application\Shared\Exception;

use RuntimeException;

/**
 * Raised when an authenticated caller reaches for something owned by another
 * account. Rendered as 404 rather than 403 so the API does not confirm that an
 * unfamiliar id exists.
 */
final class AuthorizationFailed extends RuntimeException
{
    public static function forResource(string $resource, string $id): self
    {
        return new self(sprintf('The authenticated account may not access %s "%s".', $resource, $id));
    }
}
