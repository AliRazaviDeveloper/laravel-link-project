<?php

declare(strict_types=1);

namespace Shortwave\Application\Shared\Contract;

/**
 * The slice of caching the application layer is allowed to know about.
 *
 * Narrower than any real cache client on purpose: handlers can memoise and
 * invalidate, but cannot reach for locks, tags or Lua, which keeps cache
 * behaviour reviewable from the use case alone.
 */
interface CacheStore
{
    /**
     * @template T
     *
     * @param  callable(): T  $resolver
     * @return T
     */
    public function remember(string $key, int $ttlSeconds, callable $resolver): mixed;

    public function get(string $key): mixed;

    public function put(string $key, mixed $value, int $ttlSeconds): void;

    public function forget(string $key): void;

    /**
     * Invalidates everything filed under a namespace by rolling its version
     * counter, which is O(1) regardless of how many keys are affected.
     */
    public function flushNamespace(string $namespace): void;

    /**
     * Stores only if the key is absent. Returns false when it already existed —
     * the primitive behind idempotency keys and one-shot jobs.
     */
    public function add(string $key, mixed $value, int $ttlSeconds): bool;
}
