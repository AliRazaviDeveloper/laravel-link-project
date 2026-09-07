<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\DTO;

/**
 * Result of consulting the resolution cache.
 *
 * "Not cached" and "cached as nonexistent" have to be distinguishable, and a
 * nullable return cannot express both, so the outcome is explicit.
 */
final readonly class ResolutionLookup
{
    private function __construct(
        public CacheOutcome $outcome,
        public ?Resolution $resolution,
    ) {}

    public static function hit(Resolution $resolution): self
    {
        return new self(CacheOutcome::Hit, $resolution->asCacheHit());
    }

    public static function knownAbsent(): self
    {
        return new self(CacheOutcome::NegativeHit, null);
    }

    public static function miss(): self
    {
        return new self(CacheOutcome::Miss, null);
    }

    public function isMiss(): bool
    {
        return $this->outcome === CacheOutcome::Miss;
    }

    public function isKnownAbsent(): bool
    {
        return $this->outcome === CacheOutcome::NegativeHit;
    }
}
