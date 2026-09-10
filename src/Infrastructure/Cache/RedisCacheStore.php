<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository;
use Shortwave\Application\Shared\Contract\CacheStore;

/**
 * Redis-backed implementation of the application's cache port.
 *
 * The one piece of real machinery here is namespace versioning. Laravel's cache
 * tags work, but on Redis they maintain a set per tag that has to be walked to
 * invalidate, which grows without bound on a key space as wide as ours
 * (link x window x granularity x dimensions). Instead each namespace carries an
 * integer version that is baked into every key; bumping it orphans the whole
 * generation at once and lets Redis reclaim the old keys as their TTLs lapse.
 *
 * The cost is that invalidation is all-or-nothing per namespace, which is exactly
 * why resolutions and reports use separate ones.
 */
final class RedisCacheStore implements CacheStore
{
    private const string VERSION_PREFIX = 'ns-version:';

    /**
     * Version lookups are memoised per request: a list endpoint would otherwise
     * fetch the same version once per key it builds.
     *
     * @var array<string, int>
     */
    private array $versions = [];

    public function __construct(private readonly Repository $cache) {}

    public function remember(string $key, int $ttlSeconds, callable $resolver): mixed
    {
        // Wrapped rather than passed straight through: the framework's signature asks
        // for a Closure, and a bare callable would not satisfy it.
        return $this->cache->remember(
            $this->qualify($key),
            $ttlSeconds,
            static fn (): mixed => $resolver(),
        );
    }

    public function get(string $key): mixed
    {
        return $this->cache->get($this->qualify($key));
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        if ($ttlSeconds <= 0) {
            return;
        }

        $this->cache->put($this->qualify($key), $value, $ttlSeconds);
    }

    public function forget(string $key): void
    {
        $this->cache->forget($this->qualify($key));
    }

    public function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        return $this->cache->add($this->qualify($key), $value, $ttlSeconds);
    }

    public function flushNamespace(string $namespace): void
    {
        $versionKey = self::VERSION_PREFIX.$namespace;

        // `increment` on a missing key is a no-op on some stores, so seed it first.
        // The seed is intentionally 1, not 0, so a flush before any write still
        // produces a version distinct from the implicit default.
        $this->cache->add($versionKey, 1, $this->versionTtl());

        $next = $this->cache->increment($versionKey);
        $this->versions[$namespace] = is_numeric($next) ? (int) $next : 1;
    }

    /**
     * Prefixes a key with the current version of its namespace.
     *
     * A key with no `namespace:rest` shape is left alone; that only happens for
     * keys written outside the two namespaced caches.
     */
    private function qualify(string $key): string
    {
        $separator = strpos($key, ':');

        if ($separator === false) {
            return $key;
        }

        $namespace = substr($key, 0, $separator);

        return sprintf('%s:v%d:%s', $namespace, $this->versionOf($namespace), substr($key, $separator + 1));
    }

    private function versionOf(string $namespace): int
    {
        if (isset($this->versions[$namespace])) {
            return $this->versions[$namespace];
        }

        $stored = $this->cache->get(self::VERSION_PREFIX.$namespace);

        return $this->versions[$namespace] = is_numeric($stored) ? (int) $stored : 1;
    }

    /**
     * Version counters must outlive the entries they qualify, otherwise an expired
     * counter would reset to 1 and resurrect a generation of stale keys.
     */
    private function versionTtl(): int
    {
        return 30 * 86_400;
    }
}
