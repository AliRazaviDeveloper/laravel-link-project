<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Shortwave\Presentation\Http\Middleware\EnforceJsonResponse;
use Shortwave\Presentation\Http\Middleware\IdempotencyKey;
use Shortwave\Presentation\Http\Middleware\RequestCorrelation;
use Shortwave\Presentation\Http\Problem\ProblemDetailsRenderer;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/public.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withCommands([
        __DIR__.'/../src/Presentation/Console/Command',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            RequestCorrelation::class,
            EnforceJsonResponse::class,
        ]);

        $middleware->alias([
            'idempotent' => IdempotencyKey::class,
            // Sanctum ships these but no longer registers them itself.
            // `ability` passes when the token holds any one of the listed abilities;
            // `abilities` requires all of them.
            'ability' => CheckForAnyAbility::class,
            'abilities' => CheckAbilities::class,
        ]);

        // Bearer tokens are the only credential; no cookie/session stack needed.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ProblemDetailsRenderer::register($exceptions);
    })
    ->create();

/*
 * Application code lives in src/, not app/. The framework derives its notion of the
 * application namespace by matching this path against a PSR-4 root in composer.json,
 * so the two have to agree — "Shortwave\" => "src/" is the other half of this line.
 */
$app->useAppPath($app->basePath('src'));

return $app;
