<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
|
| This API is stateless and the session middleware is not registered. The file
| exists because a handful of framework internals read from it during boot;
| the array driver keeps that from touching Redis or the database.
|
*/

return [
    'driver' => 'array',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'connection' => null,
    'table' => 'sessions',
    'store' => null,
    'lottery' => [2, 100],
    'cookie' => 'shortwave_session',
    'path' => '/',
    'domain' => null,
    'secure' => true,
    'http_only' => true,
    'same_site' => 'lax',
];
