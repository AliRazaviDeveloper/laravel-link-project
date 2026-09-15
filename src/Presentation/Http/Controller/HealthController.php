<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Controller;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use MongoDB\Laravel\Connection as MongoConnection;
use RuntimeException;
use Throwable;

/**
 * Readiness probe covering all three backing services.
 *
 * Every dependency is checked even after one has already failed, so a single
 * response tells you everything that is down rather than only the first thing. A
 * degraded result answers 503 so an orchestrator will pull the container out of
 * rotation instead of sending it traffic it cannot serve.
 */
final class HealthController extends Controller
{
    public function __invoke(DatabaseManager $database, RedisFactory $redis): JsonResponse
    {
        $checks = [
            'postgres' => $this->check(fn () => $database->connection()->select('select 1')),
            'mongodb' => $this->check(fn () => $this->mongo($database)->getMongoClient()->listDatabaseNames()),
            'redis' => $this->check(fn () => $redis->connection()->ping()),
        ];

        $healthy = ! in_array(false, array_map(
            static fn (array $check): bool => $check['ok'],
            $checks,
        ), true);

        return new JsonResponse([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function mongo(DatabaseManager $database): MongoConnection
    {
        $connection = $database->connection('mongodb');

        if (! $connection instanceof MongoConnection) {
            throw new RuntimeException('The "mongodb" connection is not a MongoDB connection.');
        }

        return $connection;
    }

    /**
     * @return array{ok: bool, latency_ms: float, error?: string}
     */
    private function check(callable $probe): array
    {
        $startedAt = hrtime(true);

        try {
            $probe();

            return [
                'ok' => true,
                'latency_ms' => $this->elapsedMs($startedAt),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'latency_ms' => $this->elapsedMs($startedAt),
                // Only the class name: a connection exception message routinely
                // contains the DSN, credentials included.
                'error' => class_basename($exception),
            ];
        }
    }

    private function elapsedMs(float|int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 2);
    }
}
