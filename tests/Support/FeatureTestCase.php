<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;
use MongoDB\Laravel\Connection as MongoConnection;

/**
 * Base class for tests that exercise the real stack.
 *
 * All three stores are real. Mocking them out would leave the interesting parts —
 * the unique slug constraint, the aggregation pipelines, the atomicity of the Redis
 * counters — untested, and those are exactly the places where this design either
 * works or does not.
 *
 * The price is isolation, which has to be arranged by hand for two of the three:
 * RefreshDatabase covers Postgres, while Mongo collections and Redis keys are
 * cleared per test below.
 */
abstract class FeatureTestCase extends BaseTestCase
{
    use ApiHelpers;
    use CreatesApplication;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flushRedis();
        $this->flushMongo();
        $this->syncMongoIndexes();
    }

    protected function flushRedis(): void
    {
        // Guarded by the database number in phpunit.xml; flushing a shared Redis
        // would take the developer's own data with it.
        Redis::connection()->flushdb();
    }

    protected function flushMongo(): void
    {
        $connection = $this->mongo();

        foreach ($connection->getMongoDB()->listCollectionNames() as $collection) {
            $connection->getCollection($collection)->deleteMany([]);
        }
    }

    /**
     * Indexes are created once per process, not per test: creation is idempotent but
     * not free, and the aggregation tests need them to exercise the same plans
     * production does.
     */
    protected function syncMongoIndexes(): void
    {
        static $synced = false;

        if ($synced) {
            return;
        }

        $this->artisan('shortwave:mongo-sync');
        $synced = true;
    }

    protected function mongo(): MongoConnection
    {
        $connection = $this->app->make('db')->connection('mongodb');

        if (! $connection instanceof MongoConnection) {
            $this->fail('The mongodb test connection is not a MongoDB connection.');
        }

        return $connection;
    }
}
