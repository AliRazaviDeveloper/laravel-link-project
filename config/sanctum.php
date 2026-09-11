<?php

declare(strict_types=1);

return [

    /*
     * Empty: stateful (cookie) authentication is not used at all. Any value here
     * would open a CSRF-relevant path that this API has no need for.
     */
    'stateful' => [],

    'guard' => [],

    /*
     * Tokens do not expire by default — an API key that stops working overnight is a
     * worse failure mode for a machine client than a long-lived one. Set
     * SANCTUM_EXPIRATION where a policy requires rotation.
     */
    'expiration' => env('SANCTUM_EXPIRATION') !== null ? (int) env('SANCTUM_EXPIRATION') : null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'sw_'),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
