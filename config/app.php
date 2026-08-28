<?php

declare(strict_types=1);

return [

    'name' => env('APP_NAME', 'Shortwave'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),

    /*
     * UTC everywhere, with no exception for display: this is an API, and a client
     * that wants local time converts an RFC 3339 timestamp itself. Storing anything
     * else is how time-bucketed analytics ends up with duplicated or missing hours
     * twice a year.
     */
    'timezone' => 'UTC',

    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',

    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
