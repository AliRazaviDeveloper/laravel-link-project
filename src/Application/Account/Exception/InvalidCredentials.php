<?php

declare(strict_types=1);

namespace Shortwave\Application\Account\Exception;

use RuntimeException;

/**
 * Raised for both an unknown email and a wrong password, deliberately without
 * distinguishing them, so the endpoint cannot be used to enumerate accounts.
 */
final class InvalidCredentials extends RuntimeException
{
    public static function create(): self
    {
        return new self('The email address or password is incorrect.');
    }
}
