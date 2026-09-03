<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\ValueObject;

/**
 * Where the click came from, reduced to a host.
 *
 * Full referring URLs routinely carry session tokens and search terms in their
 * query strings, so only the host survives the trip into storage.
 */
final readonly class Referrer
{
    public const string DIRECT = 'direct';

    private function __construct(public string $host) {}

    public static function direct(): self
    {
        return new self(self::DIRECT);
    }

    public static function fromHeader(?string $header): self
    {
        $trimmed = trim($header ?? '');

        if ($trimmed === '') {
            return self::direct();
        }

        $host = parse_url($trimmed, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return self::direct();
        }

        $host = strtolower($host);

        return new self(str_starts_with($host, 'www.') ? substr($host, 4) : $host);
    }

    /**
     * Rebuilds from an already-reduced host, as stored in the click buffer or the
     * analytics document. Skips the URL parsing that `fromHeader` does, because the
     * value has been through it once already.
     */
    public static function fromHost(?string $host): self
    {
        $normalised = strtolower(trim($host ?? ''));

        return $normalised === '' ? self::direct() : new self($normalised);
    }

    public function isDirect(): bool
    {
        return $this->host === self::DIRECT;
    }
}
