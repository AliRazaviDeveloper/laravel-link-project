<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shortwave
|--------------------------------------------------------------------------
|
| Application-specific knobs. Framework configuration stays in its own files;
| anything here is a decision about how this service behaves.
|
*/

return [

    /*
     * The origin short links are built with. Usually a shorter host than the API's,
     * which is the entire point of the product, so it is configured separately from
     * APP_URL rather than derived from it.
     */
    'short_domain' => env('SHORTWAVE_SHORT_DOMAIN', env('APP_URL', 'http://localhost')),

    'slug' => [
        /*
         * Length of a generated slug. Raising this is safe at any time; lowering it
         * increases the collision rate against links that already exist.
         */
        'length' => (int) env('SHORTWAVE_SLUG_LENGTH', 7),
    ],

    'cache' => [
        'resolution' => [
            /*
             * How long a resolved slug stays cached. An hour is long enough that a
             * popular link is served from memory almost always, and short enough
             * that a missed invalidation self-corrects within the hour.
             */
            'hit_ttl' => (int) env('SHORTWAVE_RESOLUTION_TTL', 3600),

            /*
             * Negative caching for slugs that do not exist. Deliberately brief: this
             * is the window in which a newly created slug would still 404 for a
             * client that probed it moments earlier.
             */
            'absent_ttl' => (int) env('SHORTWAVE_ABSENT_TTL', 30),
        ],
    ],

    'redis' => [
        /*
         * Connection used for click counters and the analytics buffer. Kept separate
         * in configuration so these can be moved to their own Redis instance without
         * touching code — they have a very different access pattern to the cache.
         */
        'connection' => env('SHORTWAVE_REDIS_CONNECTION', 'default'),
    ],

    'analytics' => [
        /*
         * Secret for the daily visitor fingerprint. Rotating it resets unique-visitor
         * counts from that day forward, which is why it is not tied to APP_KEY:
         * rotating an application key should not silently distort analytics.
         */
        'fingerprint_secret' => env('SHORTWAVE_FINGERPRINT_SECRET', env('APP_KEY', 'insecure-development-secret')),

        /*
         * Clicks buffered before a batch is queued. Larger batches mean fewer, bigger
         * Mongo inserts and a slightly longer delay before a click is visible.
         */
        'flush_batch' => (int) env('SHORTWAVE_FLUSH_BATCH', 100),

        /*
         * Retention for raw click documents. The TTL index is built from this value,
         * so shortening it takes effect on the next index sync.
         */
        'retention_days' => (int) env('SHORTWAVE_RETENTION_DAYS', 400),
    ],

];
