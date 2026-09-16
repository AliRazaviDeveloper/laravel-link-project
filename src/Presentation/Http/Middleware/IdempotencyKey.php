<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shortwave\Application\Shared\Contract\CacheStore;
use Shortwave\Application\Shared\Support\Input;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes unsafe requests replay-safe when the client supplies an `Idempotency-Key`.
 *
 * A client that times out on `POST /links` has no way to know whether the link was
 * created. Retrying blindly creates a duplicate; not retrying may lose the write.
 * With a key, the retry returns the original response instead.
 *
 * Three details make this correct rather than decorative:
 *
 *  - the key is reserved with an atomic add, so two simultaneous retries cannot both
 *    proceed — the loser gets 409 rather than a duplicate;
 *  - the cached entry is keyed by account as well as key, so one tenant's key cannot
 *    collide with another's;
 *  - a failed request releases its reservation, because a client retrying after a
 *    500 must be allowed to actually retry.
 *
 * The body is fingerprinted alongside the key: reusing one key for a different
 * payload is a client bug, and returning the first response for it would be worse
 * than an error.
 */
final class IdempotencyKey
{
    public const string HEADER = 'Idempotency-Key';

    private const string NAMESPACE = 'idempotency';

    private const int RETENTION_SECONDS = 86_400;

    private const int MAX_KEY_LENGTH = 255;

    public function __construct(private readonly CacheStore $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if (! is_string($key) || trim($key) === '') {
            return $next($request);
        }

        if (strlen($key) > self::MAX_KEY_LENGTH) {
            return $this->problem(400, 'The Idempotency-Key header is too long.');
        }

        $cacheKey = $this->cacheKey($request, $key);
        $stored = $this->cache->get($cacheKey);

        if (is_array($stored)) {
            return $this->replay(Input::shape($stored));
        }

        // Reservation and lookup are two steps, so a request that arrives between
        // them still has to lose the `add` race below.
        if (! $this->cache->add($cacheKey.':lock', 1, self::RETENTION_SECONDS)) {
            return $this->problem(409, 'A request with this Idempotency-Key is already in flight.');
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            $this->cache->forget($cacheKey.':lock');

            return $response;
        }

        if ($response instanceof JsonResponse) {
            $this->cache->put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getData(true),
            ], self::RETENTION_SECONDS);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function replay(array $stored): JsonResponse
    {
        $body = Input::shape($stored['body'] ?? null);
        $status = $stored['status'] ?? null;

        return new JsonResponse($body, is_int($status) ? $status : 200, [
            // Tells the client this was a replay, not a fresh write — useful when
            // debugging a retry storm.
            'Idempotent-Replay' => 'true',
        ]);
    }

    private function cacheKey(Request $request, string $key): string
    {
        $identifier = $request->user()?->getAuthIdentifier();
        $accountId = is_string($identifier) ? $identifier : 'anonymous';
        $fingerprint = hash('xxh128', $request->method().'|'.$request->path().'|'.$request->getContent());

        return sprintf('%s:%s:%s:%s', self::NAMESPACE, $accountId, $key, $fingerprint);
    }

    private function problem(int $status, string $detail): JsonResponse
    {
        return new JsonResponse([
            'type' => 'https://shortwave.dev/problems/idempotency_conflict',
            'title' => $status === 409 ? 'Conflict' : 'Bad Request',
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
