<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\ValueObject;

use Shortwave\Domain\Shared\Exception\InvariantViolation;
use Stringable;

/**
 * A validated outbound URL.
 *
 * A shortener is an open redirector by design, which makes it an attractive tool
 * for reaching hosts the caller could not reach directly. The checks here keep
 * loopback, link-local and RFC 1918 targets out of the database entirely rather
 * than relying on the redirect handler to notice them later.
 */
final readonly class Destination implements Stringable
{
    public const int MAX_LENGTH = 2048;

    private const array ALLOWED_SCHEMES = ['http', 'https'];

    private const array BLOCKED_HOSTS = ['localhost', 'metadata.google.internal'];

    private function __construct(
        public string $value,
        public string $host,
        public string $scheme,
    ) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvariantViolation::for('destination_url', 'A destination URL is required.');
        }

        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw InvariantViolation::for('destination_url', sprintf(
                'A destination URL may not exceed %d characters.',
                self::MAX_LENGTH,
            ));
        }

        $parts = parse_url($trimmed);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw InvariantViolation::for('destination_url', 'The destination must be an absolute URL.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw InvariantViolation::for('destination_url', sprintf(
                'Only %s URLs can be shortened.',
                implode(' and ', self::ALLOWED_SCHEMES),
            ));
        }

        $host = strtolower($parts['host']);

        if (self::isNotRoutable($host)) {
            throw InvariantViolation::for(
                'destination_url',
                'The destination host is not publicly routable.',
            );
        }

        return new self($trimmed, $host, $scheme);
    }

    public function equals(?self $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    /**
     * Registrable-ish host used for grouping in analytics; good enough for
     * reporting without pulling in a public-suffix list.
     */
    public function apexHost(): string
    {
        $labels = explode('.', $this->host);

        return count($labels) <= 2 ? $this->host : implode('.', array_slice($labels, -2));
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function isNotRoutable(string $host): bool
    {
        if (in_array($host, self::BLOCKED_HOSTS, true) || str_ends_with($host, '.localhost')) {
            return true;
        }

        $ip = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP);

        if ($ip === false) {
            // A hostname: DNS is resolved by the egress layer, not here, because
            // a lookup at write time can be poisoned before the redirect fires.
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
