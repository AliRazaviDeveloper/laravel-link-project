<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shortwave\Presentation\Http\Controller\DocsController;
use Shortwave\Presentation\Http\Controller\RedirectController;

/*
|--------------------------------------------------------------------------
| API reference
|--------------------------------------------------------------------------
|
| Registered before the redirect so `docs` cannot be shadowed by a slug. `docs` is
| also on the Slug value object's reserved list, so one could never be created.
|
*/

Route::get('/docs', [DocsController::class, 'ui'])->name('docs.ui');
Route::get('/docs/openapi.yaml', [DocsController::class, 'spec'])->name('docs.spec');

/*
|--------------------------------------------------------------------------
| Public redirect surface
|--------------------------------------------------------------------------
|
| One route, and it must stay that way: anything else registered at the root
| would shadow a slug. The pattern mirrors the Slug value object rather than
| accepting {slug} loosely, so a malformed path 404s at the router instead of
| reaching the handler and costing a cache lookup.
|
| Registered last so /api and /up win when they overlap.
|
*/

Route::get('/{slug}', RedirectController::class)
    ->where('slug', '[A-Za-z0-9][A-Za-z0-9_-]{1,46}[A-Za-z0-9]')
    ->middleware('throttle:redirects')
    ->name('redirect');
