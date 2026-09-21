<?php

declare(strict_types=1);

namespace Tests\Support;

use Shortwave\Application\Shared\Contract\CacheStore;

/**
 * A complete, honest CacheStore for unit tests.
 *
 * Written by hand rather than mocked because the tests that use it assert on
 * behaviour across several calls — store then look up, add twice, roll a namespace —
 * and a mock would only record that methods were called. It also exposes the raw
 * stored value and TTL, which is how the cache-format assertions are made.
 */
final class InMemoryCacheStore implements CacheStore
{
    /** @var array<string, array{value: mixed, ttl: int}> */
    private array $entries = [];

    /** @var array<string, int> */
    private array $versions = [];

    public function remember(string $key, int $ttlSeconds, callable $resolver): mixed
    {
        $qualified = $this->qualify($key);

        if (array_key_exists($qualified, $this->entries)) {
            return $this->entries[$qualified]['value'];
        }

        $value = $resolver();
        $this->entries[$qualified] = ['value' => $value, 'ttl' => $ttlSeconds];

        return $value;
    }

    public function get(string $key): mixed
    {
        return $this->entries[$this->qualify($key)]['value'] ?? null;
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        if ($ttlSeconds <= 0) {
            return;
        }

        $this->entries[$this->qualify($key)] = ['value' => $value, 'ttl' => $ttlSeconds];
    }

    public function forget(string $key): void
    {
        unset($this->entries[$this->qualify($key)]);
    }

    public function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        $qualified = $this->qualify($key);

        if (array_key_exists($qualified, $this->entries)) {
            return false;
        }

        $this->entries[$qualified] = ['value' => $value, 'ttl' => $ttlSeconds];

        return true;
    }

    /**
     * Mirrors the Redis implementation: bumping a version orphans the generation
     * rather than deleting keys, so old entries stay in the array but stop being
     * reachable.
     */
    public function flushNamespace(string $namespace): void
    {
        $this->versions[$namespace] = ($this->versions[$namespace] ?? 1) + 1;
    }

    public function rawValueFor(string $key): mixed
    {
        return $this->entries[$this->qualify($key)]['value'] ?? null;
    }

    public function ttlFor(string $key): ?int
    {
        return $this->entries[$this->qualify($key)]['ttl'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->entries);
    }

    private function qualify(string $key): string
    {
        $separator = strpos($key, ':');

        if ($separator === false) {
            return $key;
        }

        $namespace = substr($key, 0, $separator);

        return sprintf('%s:v%d:%s', $namespace, $this->versions[$namespace] ?? 1, substr($key, $separator + 1));
    }
}
