<?php

declare(strict_types=1);

namespace Shortwave\Domain\Shared\ValueObject;

use Shortwave\Domain\Shared\Exception\InvariantViolation;
use Stringable;

/**
 * ULID-shaped identity. Lexicographically sortable, so Postgres indexes stay
 * append-friendly and Mongo can range-scan by id instead of by timestamp.
 */
abstract readonly class Identifier implements Stringable
{
    private const string PATTERN = '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/';

    final private function __construct(public string $value) {}

    public static function fromString(string $value): static
    {
        $normalised = strtoupper(trim($value));

        if (preg_match(self::PATTERN, $normalised) !== 1) {
            throw InvariantViolation::for(
                static::field(),
                sprintf('"%s" is not a valid %s.', $value, static::label()),
            );
        }

        return new static($normalised);
    }

    public function equals(?self $other): bool
    {
        return $other instanceof static && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    protected static function label(): string
    {
        $parts = explode('\\', static::class);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', end($parts)) ?? 'identifier');
    }

    protected static function field(): string
    {
        return 'id';
    }
}
