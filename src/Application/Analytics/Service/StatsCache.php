<?php

declare(strict_types=1);

namespace Shortwave\Application\Analytics\Service;

use Shortwave\Application\Analytics\DTO\LinkStatsView;
use Shortwave\Application\Shared\Contract\CacheStore;

/**
 * Memoises aggregation results.
 *
 * Kept separate from ResolutionCache because the two have opposite risk profiles:
 * a stale redirect sends someone to the wrong page, while a stale chart is merely
 * a chart from a minute ago. That difference is why this one is allowed generous
 * TTLs and coarse, namespace-wide invalidation.
 */
final readonly class StatsCache
{
    public const string NAMESPACE = 'stats';

    public function __construct(private CacheStore $cache) {}

    /**
     * @param  callable(): LinkStatsView  $compute
     */
    public function remember(string $key, int $ttlSeconds, callable $compute): LinkStatsView
    {
        $namespaced = self::NAMESPACE.':'.$key;
        $cached = $this->cache->get($namespaced);

        if ($cached instanceof LinkStatsView) {
            return $cached->withCacheFlag(true);
        }

        $fresh = $compute();
        $this->cache->put($namespaced, $fresh, $ttlSeconds);

        return $fresh;
    }

    /**
     * Rolls the namespace version, retiring every cached report at once.
     *
     * Used when a backfill or a purge changes history. Per-key invalidation is not
     * offered on purpose: the key space is a product of link, window, granularity
     * and dimensions, so enumerating the affected entries is impractical, and
     * getting it wrong leaves a stale report behind indefinitely.
     */
    public function flushAll(): void
    {
        $this->cache->flushNamespace(self::NAMESPACE);
    }
}
