<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Queue;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Shortwave\Application\Analytics\DTO\ClickContext;
use Shortwave\Application\Shared\Contract\ClickRecorder;

/**
 * Collects clicks in a Redis list and dispatches them a batch at a time.
 *
 * Queueing one job per redirect would make the analytics queue as busy as the
 * traffic itself — a job payload, a worker wake-up and a Mongo insert for every
 * click. Pushing onto a list is one command; when the list reaches the flush size
 * the pushing request trims off a batch and hands it to a single job.
 *
 * The push and the length check are pipelined into one round trip, and RPUSH's
 * return value gives the new length for free, so no extra LLEN is needed. If the
 * process dies between the trim and the dispatch, that batch is lost: an
 * acceptable trade for a click log, and the reason totals are documented as
 * approximate rather than exact.
 */
final readonly class BufferedClickRecorder implements ClickRecorder
{
    private const string BUFFER_KEY = 'clicks:buffer';

    public function __construct(
        private RedisFactory $redis,
        private Dispatcher $dispatcher,
        private int $batchSize = 100,
        private string $connection = 'default',
    ) {}

    public function record(ClickContext $context): void
    {
        if (! $context->isAttributed()) {
            return;
        }

        $client = $this->redis->connection($this->connection);
        $length = (int) $client->rpush(self::BUFFER_KEY, json_encode($context->toArray(), JSON_THROW_ON_ERROR));

        if ($length < $this->batchSize) {
            return;
        }

        $this->flush();
    }

    /**
     * Also called by the scheduler, so a buffer that never fills — a quiet night,
     * a low-traffic account — still reaches the store within a minute.
     */
    public function flush(): int
    {
        $client = $this->redis->connection($this->connection);

        // LPOP with a count is atomic, so two concurrent flushes take disjoint
        // slices rather than both claiming the same head of the list.
        $payloads = $client->lpop(self::BUFFER_KEY, $this->batchSize);

        if (! is_array($payloads) || $payloads === []) {
            return 0;
        }

        /** @var list<array<string, string|int>> $rows */
        $rows = [];

        foreach ($payloads as $payload) {
            if (! is_string($payload)) {
                continue;
            }

            $decoded = json_decode($payload, true);

            if (is_array($decoded)) {
                /** @var array<string, string|int> $decoded */
                $rows[] = $decoded;
            }
        }

        if ($rows === []) {
            return 0;
        }

        $this->dispatcher->dispatch(new RecordClickJob($rows));

        return count($rows);
    }
}
