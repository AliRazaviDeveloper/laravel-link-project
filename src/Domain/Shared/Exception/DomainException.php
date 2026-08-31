<?php

declare(strict_types=1);

namespace Shortwave\Domain\Shared\Exception;

use RuntimeException;

/**
 * Base class for every failure the domain raises on purpose.
 *
 * The `errorCode` is part of the API contract: the HTTP layer maps it straight
 * into the `type` member of an RFC 9457 problem document, so renaming one is a
 * breaking change for clients.
 */
abstract class DomainException extends RuntimeException
{
    abstract public function errorCode(): string;

    /**
     * Machine-readable detail carried alongside the message.
     *
     * @return array<string, scalar|array<int|string, scalar>|null>
     */
    public function context(): array
    {
        return [];
    }
}
