<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces JSON negotiation on the API surface.
 *
 * Without this, a client that forgets `Accept: application/json` gets HTML error
 * pages from the framework's default rendering — the single most common confusing
 * first experience of a Laravel API.
 */
final class EnforceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
