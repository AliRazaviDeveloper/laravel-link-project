<?php

declare(strict_types=1);

use Shortwave\Infrastructure\Persistence\Eloquent\Model\UserModel;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'sanctum'),
        'passwords' => 'users',
    ],

    'guards' => [
        /*
         * Bearer tokens only. There is no session or cookie guard, because there is
         * no browser client: leaving one configured would be an authentication path
         * nothing uses and nobody tests.
         */
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            /*
             * The Eloquent model, not the Account entity: the guard needs
             * Authenticatable, and that framework requirement is confined to the
             * infrastructure layer.
             */
            'model' => UserModel::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
