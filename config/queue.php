<?php

declare(strict_types=1);

return [

    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            /*
             * Longer than RecordClickJob's own timeout, so a job that is merely slow
             * is not handed to a second worker while the first is still writing.
             */
            'retry_after' => 90,
            'block_for' => 5,
            'after_commit' => false,
        ],

    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        /*
         * Failed jobs go to Postgres, not Redis: the whole point of inspecting them
         * is that they survive a Redis flush or restart.
         */
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],

];
