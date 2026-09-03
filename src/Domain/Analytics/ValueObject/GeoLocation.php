<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\ValueObject;

/**
 * Country-level origin, as supplied by the edge (CDN header) rather than looked
 * up here. City-level data is not collected.
 */
final readonly class GeoLocation
{
    public const string UNKNOWN = 'unknown';

    private function __construct(public string $country) {}

    public static function unknown(): self
    {
        return new self(self::UNKNOWN);
    }

    public static function fromCountryCode(?string $code): self
    {
        $normalised = strtoupper(trim($code ?? ''));

        if (preg_match('/^[A-Z]{2}$/', $normalised) !== 1) {
            return self::unknown();
        }

        return new self($normalised);
    }

    public function isKnown(): bool
    {
        return $this->country !== self::UNKNOWN;
    }
}
