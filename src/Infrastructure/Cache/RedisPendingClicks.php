<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Cache;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Redis;
use RuntimeException;
use Shortwave\Application\Link\Port\PendingClicks;
use Shortwave\Domain\Link\ValueObject\LinkId;

/**
 * Redis implementation of the click buffer.
 *
 * Goes to the Redis connection directly rather than through the cache repository,
 * because the operations that make this safe — INCR, a dirty set, and an atomic
 * read-and-reset — are not part of a cache abstraction and emulating them with
 * get/put would reintroduce the lost-update race this exists to avoid.
 *
 * A dirty set accompanies the counters so draining never needs SCAN or KEYS: the
 * flush job pops ids from the set instead of walking the keyspace, which keeps the
 * work proportional to the number of links that actually saw traffic.
 */
final readonly class RedisPendingClicks implements PendingClicks
{
    private const string COUNTER_PREFIX = 'clicks:link:';

    private const string DIRTY_SET = 'clicks:dirty';

    /**
     * Reads a counter and deletes it in one step, so two concurrent flush workers
     * cannot both claim the same clicks.
     */
    private const string DRAIN_SCRIPT = <<<'LUA'
        local total = redis.call('GET', KEYS[1])
        if total then
            redis.call('DEL', KEYS[1])
            return total
        end
        return 0
    LUA;

    public function __construct(
        private RedisFactory $redis,
        private string $connection = 'default',
    ) {}

    public function increment(LinkId $linkId): int
    {
        $client = $this->client();
        $key = $this->counterKey($linkId->value);

        // Pipelined: the counter and the dirty-set membership are both needed on
        // every click, and sending them together halves the round trips.
        // The callback is handed the raw phpredis client in pipeline mode, not the
        // Laravel connection wrapper. It still carries the configured key prefix, so
        // commands issued here are namespaced exactly as elsewhere.
        /** @var list<mixed> $results */
        $results = $client->pipeline(function (Redis $pipe) use ($key, $linkId): void {
            $pipe->incr($key);
            $pipe->sAdd(self::DIRTY_SET, $linkId->value);
        });

        return isset($results[0]) && is_numeric($results[0]) ? (int) $results[0] : 0;
    }

    public function pendingFor(LinkId $linkId): int
    {
        $value = $this->client()->get($this->counterKey($linkId->value));

        return is_numeric($value) ? (int) $value : 0;
    }

    public function pendingForMany(array $linkIds): array
    {
        if ($linkIds === []) {
            return [];
        }

        $keys = array_map($this->counterKey(...), $linkIds);

        /** @var list<mixed> $values */
        $values = $this->client()->mget($keys);

        $pending = [];

        foreach (array_values($linkIds) as $index => $linkId) {
            $value = $values[$index] ?? null;
            $pending[$linkId] = is_numeric($value) ? (int) $value : 0;
        }

        return $pending;
    }

    public function drain(int $limit): array
    {
        $client = $this->client();

        // SPOP with a count answers with an array; without one, a bare member. The
        // count is always passed here, but a falsy reply still has to be tolerated
        // because an empty set answers with an empty array on some versions and
        // `false` on others.
        $popped = $client->spop(self::DIRTY_SET, max(1, $limit));
        $linkIds = is_array($popped) ? array_values(array_filter($popped, 'is_string')) : [];

        if ($linkIds === []) {
            return [];
        }

        $counts = [];

        foreach ($linkIds as $linkId) {
            $drained = (int) $client->eval(self::DRAIN_SCRIPT, [$this->counterKey($linkId)], 1);

            // A zero means the counter expired, or another worker took it between the
            // pop and the read. Dropping it keeps the caller's map free of no-op
            // updates; the id re-enters the dirty set on the link's next click.
            if ($drained > 0) {
                $counts[$linkId] = $drained;
            }
        }

        return $counts;
    }

    public function restore(array $counts): void
    {
        if ($counts === []) {
            return;
        }

        $this->client()->pipeline(function (Redis $pipe) use ($counts): void {
            foreach ($counts as $linkId => $delta) {
                $pipe->incrBy($this->counterKey((string) $linkId), $delta);
                $pipe->sAdd(self::DIRTY_SET, (string) $linkId);
            }
        });
    }

    private function counterKey(string $linkId): string
    {
        return self::COUNTER_PREFIX.$linkId;
    }

    /**
     * The phpredis connection specifically, not the driver-agnostic base: `pipeline()`
     * with a callback and the atomic GETDEL this class relies on are both phpredis
     * features, and pretending otherwise would only move the failure to runtime.
     */
    private function client(): PhpRedisConnection
    {
        $connection = $this->redis->connection($this->connection);

        if (! $connection instanceof PhpRedisConnection) {
            throw new RuntimeException('Shortwave requires the phpredis client; set REDIS_CLIENT=phpredis.');
        }

        return $connection;
    }
}
