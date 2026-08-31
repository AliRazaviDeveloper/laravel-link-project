<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\ValueObject;

use Shortwave\Domain\Shared\Exception\InvariantViolation;
use Stringable;

/**
 * The public path segment of a short link.
 *
 * Slugs are compared case-insensitively and stored lower-cased: users type them
 * by hand from print and screenshots, so treating `Xk4Pq` and `xk4pq` as two
 * different links would be a support burden rather than a feature.
 */
final readonly class Slug implements Stringable
{
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 48;

    /**
     * Paths the HTTP layer needs for itself, plus a few we keep back for future
     * dashboard routes.
     */
    private const array RESERVED = [
        'api', 'assets', 'admin', 'dashboard', 'docs', 'favicon.ico',
        'health', 'login', 'logout', 'metrics', 'robots.txt', 'signup',
        'static', 'status', 'up', 'webhooks',
    ];

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $normalised = strtolower(trim($value));

        if ($normalised === '') {
            throw InvariantViolation::for('slug', 'A slug cannot be empty.');
        }

        $length = strlen($normalised);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw InvariantViolation::for('slug', sprintf(
                'A slug must be between %d and %d characters long.',
                self::MIN_LENGTH,
                self::MAX_LENGTH,
            ));
        }

        if (preg_match('/^[a-z0-9][a-z0-9_-]*[a-z0-9]$/', $normalised) !== 1) {
            throw InvariantViolation::for(
                'slug',
                'A slug may contain letters, digits, hyphens and underscores, and must start and end with a letter or digit.',
            );
        }

        if (in_array($normalised, self::RESERVED, true)) {
            throw InvariantViolation::for('slug', sprintf('"%s" is reserved.', $normalised));
        }

        return new self($normalised);
    }

    public static function isReserved(string $value): bool
    {
        return in_array(strtolower(trim($value)), self::RESERVED, true);
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
