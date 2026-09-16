<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id, echoes it back, and attaches it to the log context.
 *
 * This is what makes a 500 actionable: the error body carries nothing but the id,
 * and the id leads straight to the log lines for that one request.
 *
 * An inbound `X-Request-Id` is honoured so a trace can span services, but it is
 * length-capped and stripped of anything unusual — the value ends up in log files
 * and response headers, and unfiltered client input in either is how log injection
 * and header splitting happen.
 */
final class RequestCorrelation
{
    public const string HEADER = 'X-Request-Id';

    private const int MAX_LENGTH = 64;

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveId($request);

        $request->attributes->set('request_id', $requestId);

        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    private function resolveId(Request $request): string
    {
        $supplied = $request->header(self::HEADER);

        if (is_string($supplied)) {
            $sanitised = preg_replace('/[^A-Za-z0-9._-]/', '', $supplied) ?? '';

            if ($sanitised !== '') {
                return substr($sanitised, 0, self::MAX_LENGTH);
            }
        }

        return (string) Str::uuid();
    }
}
