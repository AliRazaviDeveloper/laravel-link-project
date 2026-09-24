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
     * SANCTUM_EXPIRATION to a number of minutes where a policy requires rotation.
     *
     * The blank check is load-bearing: an unset key in .env reads as an empty string,
     * not null, and casting that to int yields a zero-minute expiry — every token
     * issued would already be expired.
     */
    'expiration' => filter_var(env('SANCTUM_EXPIRATION'), FILTER_VALIDATE_INT) ?: null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'sw_'),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
