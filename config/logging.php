<?php

declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    'default' => env('LOG_CHANNEL', 'stderr'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    'channels' => [

        /*
         * Containers log to stderr and let the platform handle collection and
         * rotation. Writing to a file inside a container means losing the logs when
         * it restarts.
         */
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'info'),
            'handler' => StreamHandler::class,
            'handler_with' => ['stream' => 'php://stderr'],
            /*
             * JSON lines: these are read by a log aggregator far more often than by a
             * person, and the request id shared by RequestCorrelation is only useful
             * if it lands as a queryable field.
             */
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/shortwave.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/emergency.log'),
        ],

    ],

];
