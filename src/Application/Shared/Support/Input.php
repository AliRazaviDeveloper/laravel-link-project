<?php

declare(strict_types=1);

namespace Shortwave\Application\Shared\Support;

use DateTimeImmutable;
use DateTimeInterface;
use Shortwave\Domain\Shared\Exception\InvariantViolation;

/**
 * Typed reads out of untrusted arrays.
 *
 * Request bodies, cache payloads and database rows all arrive as `array<string, mixed>`,
 * and the alternative to something like this is a cast at every call site — `(string)
 * $row['slug']` — which quietly turns an unexpected array into "Array" and a null into
 * an empty string.
 *
 * Every method here either returns the type it promises or raises an invariant
 * violation naming the field, so a malformed payload becomes a 422 that says which
 * key was wrong rather than a TypeError several frames later.
 */
final readonly class Input
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw InvariantViolation::for($key, sprintf('"%s" must be a string.', $key));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return self::string($data, $key);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        // Numeric strings are accepted because query parameters are always strings;
        // "12abc" is not, because silently reading it as 12 hides a client bug.
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw InvariantViolation::for($key, sprintf('"%s" must be an integer.', $key));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return self::int($data, $key);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * A timestamp already stored as a date, as opposed to a client-supplied string —
     * for that, use Timestamp::parse.
     *
     * @param  array<string, mixed>  $data
     */
    public static function dateTime(array $data, string $key): DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        throw InvariantViolation::for($key, sprintf('"%s" must be a timestamp.', $key));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function nullableDateTime(array $data, string $key): ?DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return self::dateTime($data, $key);
    }

    /**
     * Narrows a value that should already be a keyed array, for cache payloads and
     * nested request structures.
     *
     * @return array<string, mixed>
     */
    public static function shape(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
