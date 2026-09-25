<?php

declare(strict_types=1);

use Shortwave\Application\Link\DTO\CacheOutcome;
use Shortwave\Application\Link\DTO\Resolution;
use Shortwave\Application\Link\Service\ResolutionCache;
use Shortwave\Domain\Link\ValueObject\Slug;
use Tests\Support\InMemoryCacheStore;

/*
|--------------------------------------------------------------------------
| Resolution cache
|--------------------------------------------------------------------------
|
| Backed by a hand-written in-memory store rather than a mock. The behaviour worth
| pinning down is "what does the cache answer after this sequence of calls", which a
| fake answers directly, while an expectation-based mock would only assert that
| certain methods were called.
|
*/

beforeEach(function (): void {
    $this->store = new InMemoryCacheStore;
    $this->cache = new ResolutionCache($this->store, hitTtl: 3600, absentTtl: 30);
    $this->slug = Slug::fromString('promo24');
    $this->resolution = new Resolution('01HQ8V3M9XKPWT7CZR4NFGD2AB', '01HQ8V3M9XKPWT7CZR4NFGD2CD', 'https://example.com');
});

it('reports a miss when nothing is stored', function (): void {
    $lookup = $this->cache->lookup($this->slug);

    expect($lookup->outcome)->toBe(CacheOutcome::Miss)
        ->and($lookup->isMiss())->toBeTrue()
        ->and($lookup->resolution)->toBeNull();
});

it('returns a stored resolution and flags it as a hit', function (): void {
    $this->cache->store($this->slug, $this->resolution);

    $lookup = $this->cache->lookup($this->slug);

    expect($lookup->outcome)->toBe(CacheOutcome::Hit)
        ->and($lookup->resolution?->destinationUrl)->toBe('https://example.com')
        // So a caller can tell a cached answer from a fresh one.
        ->and($lookup->resolution?->cacheHit)->toBeTrue();
});

it('distinguishes a known absence from a miss', function (): void {
    $this->cache->storeAbsent($this->slug);

    $lookup = $this->cache->lookup($this->slug);

    // This is the whole reason for the tri-state: a scanner probing nonexistent slugs
    // must be answered from memory, and a nullable return could not say which of the
    // two cases applied.
    expect($lookup->isKnownAbsent())->toBeTrue()
        ->and($lookup->isMiss())->toBeFalse();
});

it('stores a flat array, never an object', function (): void {
    $this->cache->store($this->slug, $this->resolution);

    // Serialising an entity would tie the cache format to class shape and break every
    // in-flight entry on the deploy that renames a property.
    expect($this->store->rawValueFor('resolution:slug:promo24'))->toBeArray()
        ->toHaveKeys(['link_id', 'account_id', 'destination_url']);
});

it('treats an unrecognised payload shape as a miss', function (): void {
    $this->store->put('resolution:slug:promo24', ['legacy_field' => 'x'], 3600);

    // A payload written by an older release must not fail the request; the next one
    // rewrites it.
    expect($this->cache->lookup($this->slug)->isMiss())->toBeTrue();
});

it('caps the TTL at the configured hit window', function (): void {
    $this->cache->store($this->slug, $this->resolution, 99_999);

    expect($this->store->ttlFor('resolution:slug:promo24'))->toBe(3600);
});

it('honours a shorter TTL when the link expires sooner', function (): void {
    // A link expiring in five minutes must not sit in cache for an hour, or it keeps
    // resolving after it should have stopped.
    $this->cache->store($this->slug, $this->resolution, 300);

    expect($this->store->ttlFor('resolution:slug:promo24'))->toBe(300);
});

it('does not store an entry whose TTL has already run out', function (): void {
    $this->cache->store($this->slug, $this->resolution, 0);

    expect($this->cache->lookup($this->slug)->isMiss())->toBeTrue();
});

it('forgets an entry', function (): void {
    $this->cache->store($this->slug, $this->resolution);
    $this->cache->forget($this->slug);

    expect($this->cache->lookup($this->slug)->isMiss())->toBeTrue();
});

it('keys entries per slug', function (): void {
    $this->cache->store($this->slug, $this->resolution);

    expect($this->cache->lookup(Slug::fromString('other-slug'))->isMiss())->toBeTrue();
});
