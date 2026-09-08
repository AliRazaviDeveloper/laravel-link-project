<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Service;

use Shortwave\Application\Link\DTO\Resolution;
use Shortwave\Application\Link\DTO\ResolutionLookup;
use Shortwave\Application\Shared\Contract\CacheStore;
use Shortwave\Application\Shared\Support\Input;
use Shortwave\Domain\Link\ValueObject\Slug;

/**
 * The memory tier in front of slug lookups.
 *
 * Two decisions here are worth more than the code:
 *
 * 1. Absences are cached. A shortener is an open endpoint, so scanners walk it
 *    with slugs that do not exist; without a negative entry every probe becomes a
 *    Postgres query. The negative TTL is kept short so a slug created moments
 *    after someone guessed it starts working almost immediately.
 * 2. The stored payload is a flat array, never a serialised entity. Putting an
 *    object in here would tie the cache format to internal class shape and turn a
 *    harmless refactor into a deserialisation incident on deploy.
 *
 * Reads and writes are separate calls rather than one `remember()` because the
 * caller — not this class — knows whether a given link may be cached at all.
 */
final readonly class ResolutionCache
{
    public const string NAMESPACE = 'resolution';

    private const string ABSENT_MARKER = '__absent__';

    public function __construct(
        private CacheStore $cache,
        private int $hitTtl = 3_600,
        private int $absentTtl = 30,
    ) {}

    public function lookup(Slug $slug): ResolutionLookup
    {
        $cached = $this->cache->get($this->keyFor($slug));

        if ($cached === self::ABSENT_MARKER) {
            return ResolutionLookup::knownAbsent();
        }

        if (! is_array($cached)) {
            return ResolutionLookup::miss();
        }

        $resolution = Resolution::fromCacheArray(Input::shape($cached));

        // A payload shape written by an older release reads as a miss, so the
        // next request rewrites it instead of failing.
        return $resolution === null
            ? ResolutionLookup::miss()
            : ResolutionLookup::hit($resolution);
    }

    /**
     * @param  int|null  $ttlSeconds  caps the entry's life when the link has an
     *                                expiry date sooner than the default window
     */
    public function store(Slug $slug, Resolution $resolution, ?int $ttlSeconds = null): void
    {
        $ttl = min($ttlSeconds ?? $this->hitTtl, $this->hitTtl);

        if ($ttl <= 0) {
            return;
        }

        $this->cache->put($this->keyFor($slug), $resolution->toCacheArray(), $ttl);
    }

    public function storeAbsent(Slug $slug): void
    {
        $this->cache->put($this->keyFor($slug), self::ABSENT_MARKER, $this->absentTtl);
    }

    public function forget(Slug $slug): void
    {
        $this->cache->forget($this->keyFor($slug));
    }

    private function keyFor(Slug $slug): string
    {
        return self::NAMESPACE.':slug:'.$slug->value;
    }
}
