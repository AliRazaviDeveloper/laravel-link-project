<?php

declare(strict_types=1);

return [

    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [

        /*
         * The system of record: accounts, links, tokens. Chosen for the things this
         * data actually needs — a unique index on slug that is honoured under
         * concurrency, and transactions around quota checks.
         */
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'postgres'),
            'port' => (int) env('DB_PORT', 5432),
            'database' => env('DB_DATABASE', 'shortwave'),
            'username' => env('DB_USERNAME', 'shortwave'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        /*
         * The click log. A different shape of problem entirely: write-heavy,
         * append-only, never updated, queried almost exclusively through aggregation
         * over a date range. Relational storage would need partitioning and a
         * rollup table to keep up with what `$dateTrunc` does natively here.
         */
        'mongodb' => [
            'driver' => 'mongodb',
            'dsn' => env('MONGO_DSN', sprintf(
                'mongodb://%s:%s@%s:%s',
                env('MONGO_USERNAME', 'shortwave'),
                env('MONGO_PASSWORD', ''),
                env('MONGO_HOST', 'mongodb'),
                env('MONGO_PORT', '27017'),
            )),
            'database' => env('MONGO_DATABASE', 'shortwave_analytics'),
            'options' => [
                'authSource' => env('MONGO_AUTH_SOURCE', 'admin'),
                /*
                 * `majority` rather than the default: a click acknowledged by one node
                 * and then lost to an election is a silent hole in a report. Against
                 * the standalone server used in development this is simply w:1.
                 */
                'w' => 'majority',
                'retryWrites' => (bool) env('MONGO_RETRY_WRITES', false),
                'connectTimeoutMS' => 3000,
                'serverSelectionTimeoutMS' => 5000,
            ],
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'shortwave:'),
            'persistent' => (bool) env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'host' => env('REDIS_HOST', 'redis'),
            'password' => env('REDIS_PASSWORD') ?: null,
            'port' => (int) env('REDIS_PORT', 6379),
            'database' => (int) env('REDIS_DB', 0),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', 'redis'),
            'password' => env('REDIS_PASSWORD') ?: null,
            'port' => (int) env('REDIS_PORT', 6379),
            /*
             * A separate logical database so `cache:clear` cannot wipe the click
             * counters, which are not a cache and cannot be recomputed.
             */
            'database' => (int) env('REDIS_CACHE_DB', 1),
        ],

    ],

];
