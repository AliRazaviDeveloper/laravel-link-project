<?php

declare(strict_types=1);

namespace Shortwave\Application\Shared\Support;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Shortwave\Domain\Shared\Exception\InvariantViolation;

/**
 * Turns client-supplied timestamps into UTC values.
 *
 * Everything inside the domain is UTC; offsets are honoured on the way in and
 * then normalised, so two clients in different zones describing the same instant
 * produce the same stored value.
 */
final readonly class Timestamp
{
    public static function parseOptional(?string $value, string $field): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::parse($value, $field);
    }

    public static function parse(string $value, string $field): DateTimeImmutable
    {
        try {
            $parsed = new DateTimeImmutable(trim($value));
        } catch (Exception) {
            throw InvariantViolation::for($field, sprintf('"%s" is not a valid timestamp.', $value));
        }

        return $parsed->setTimezone(new DateTimeZone('UTC'));
    }
}
