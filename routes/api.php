<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shortwave\Presentation\Http\Controller\Api\V1\AuthController;
use Shortwave\Presentation\Http\Controller\Api\V1\LinkController;
use Shortwave\Presentation\Http\Controller\Api\V1\LinkImportController;
use Shortwave\Presentation\Http\Controller\Api\V1\LinkStatsController;
use Shortwave\Presentation\Http\Controller\Api\V1\OverviewController;
use Shortwave\Presentation\Http\Controller\HealthController;

/*
|--------------------------------------------------------------------------
| Authenticated API (v1)
|--------------------------------------------------------------------------
|
| Everything is namespaced under /api/v1 so a breaking change can ship as v2
| alongside it. Token abilities gate writes separately from reads, which lets a
| CI job hold a token that can create links but not read anyone's analytics.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    // Registration and login are throttled harder than the rest of the API: they are
    // the only endpoints where an attacker gains something by trying repeatedly.
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::middleware('ability:links:read')->group(function (): void {
            Route::get('links', [LinkController::class, 'index'])->name('links.index');
            Route::get('links/{link}', [LinkController::class, 'show'])->name('links.show');
        });

        // Writes carry the idempotency middleware so a client can safely retry a
        // request whose response it never saw.
        Route::middleware(['ability:links:write', 'idempotent'])->group(function (): void {
            Route::post('links', [LinkController::class, 'store'])->name('links.store');
            Route::post('links/import', LinkImportController::class)->name('links.import');
            Route::patch('links/{link}', [LinkController::class, 'update'])->name('links.update');
            Route::delete('links/{link}', [LinkController::class, 'destroy'])->name('links.destroy');
            Route::post('links/{link}/restore', [LinkController::class, 'restore'])->name('links.restore');
        });

        Route::middleware('ability:analytics:read')->group(function (): void {
            Route::get('links/{link}/stats', LinkStatsController::class)->name('links.stats');
            Route::get('overview', OverviewController::class)->name('overview');
        });
    });
});
