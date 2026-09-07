<?php

declare(strict_types=1);

namespace Shortwave\Application\Shared\Exception;

use RuntimeException;

/**
 * The random slug generator could not find a free value. In practice this means
 * the keyspace for the current slug length is saturated and the length needs
 * raising, so it is a capacity alarm rather than a user error.
 */
final class SlugExhausted extends RuntimeException
{
    public static function after(int $attempts, int $length): self
    {
        return new self(sprintf(
            'No free slug found after %d attempts at %d characters.',
            $attempts,
            $length,
        ));
    }
}
