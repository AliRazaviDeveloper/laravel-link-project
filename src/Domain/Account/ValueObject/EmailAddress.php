<?php

declare(strict_types=1);

namespace Shortwave\Domain\Account\ValueObject;

use Shortwave\Domain\Shared\Exception\InvariantViolation;
use Stringable;

final readonly class EmailAddress implements Stringable
{
    public const int MAX_LENGTH = 254;

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $normalised = strtolower(trim($value));

        if ($normalised === '' || strlen($normalised) > self::MAX_LENGTH) {
            throw InvariantViolation::for('email', 'A valid email address is required.');
        }

        if (filter_var($normalised, FILTER_VALIDATE_EMAIL) === false) {
            throw InvariantViolation::for('email', sprintf('"%s" is not a valid email address.', $value));
        }

        return new self($normalised);
    }

    public function domain(): string
    {
        return substr($this->value, (int) strrpos($this->value, '@') + 1);
    }

    public function equals(?self $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
