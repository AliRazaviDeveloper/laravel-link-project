<?php

declare(strict_types=1);

return [

    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        /*
         * The application's own store, separate from the framework's.
         *
         * Same Redis, distinct prefix: `cache:clear` during a deploy should not drop
         * every resolved slug at once and send the whole redirect surface back to
         * Postgres in one go.
         */
        'shortwave' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
            'prefix' => env('SHORTWAVE_CACHE_PREFIX', 'sw'),
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE', 'cache_locks'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

    ],

    'prefix' => env('CACHE_PREFIX', 'shortwave_cache_'),

];
