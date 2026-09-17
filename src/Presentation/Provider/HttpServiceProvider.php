<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Provider;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Shortwave\Application\Link\Handler\CreateLinkHandler;
use Shortwave\Application\Link\Handler\ListLinksHandler;
use Shortwave\Application\Link\Handler\RestoreLinkHandler;
use Shortwave\Application\Link\Handler\ShowLinkHandler;
use Shortwave\Application\Link\Handler\UpdateLinkHandler;
use Shortwave\Domain\Account\ValueObject\Plan;
use Shortwave\Infrastructure\Persistence\Eloquent\Model\UserModel;

/**
 * HTTP-layer wiring: rate limits, the auth model, and the handlers that need the
 * configured short-link domain injected.
 */
final class HttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Several handlers build absolute short URLs. The domain is configuration,
        // not a domain concept, so it is supplied here rather than read from a
        // helper deep inside the application layer.
        foreach ([
            CreateLinkHandler::class,
            ListLinksHandler::class,
            RestoreLinkHandler::class,
            ShowLinkHandler::class,
            UpdateLinkHandler::class,
        ] as $handler) {
            $this->app->when($handler)
                ->needs('$shortDomain')
                ->give(fn (): string => $this->shortDomain());
        }
    }

    public function boot(): void
    {
        $this->configureRateLimits();
    }

    private function shortDomain(): string
    {
        $configured = $this->app->make('config')->get('shortwave.short_domain');

        return is_string($configured) ? $configured : 'http://localhost';
    }

    /**
     * Three limiters, because the three surfaces have different threat models.
     *
     * The API limit is per token and scaled by plan, so a paying account is not
     * throttled at a free account's rate. Auth is per IP and deliberately tight —
     * it is the only place where guessing repeatedly pays off. Redirects are the
     * loosest by far: they are the product, and a viral link legitimately produces
     * a flood from a single CDN egress address.
     */
    private function configureRateLimits(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();

            if ($token === null) {
                return Limit::perMinute(30)->by($request->ip() ?? 'unknown');
            }

            $tokenId = $token->getAttribute('id');

            return Limit::perMinute($this->planAllowance($request))
                ->by('token:'.(is_scalar($tokenId) ? (string) $tokenId : 'unknown'));
        });

        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('auth:'.($request->ip() ?? 'unknown')));

        RateLimiter::for('redirects', fn (Request $request): Limit => Limit::perMinute(600)
            ->by('redirect:'.($request->ip() ?? 'unknown')));
    }

    /**
     * Reads the plan off the already-authenticated model rather than loading the
     * account again: a rate limiter runs before every request, and a repository
     * call here would add a query to all of them.
     */
    private function planAllowance(Request $request): int
    {
        $user = $request->user();

        if (! $user instanceof UserModel) {
            return Plan::Free->requestsPerMinute();
        }

        return (Plan::tryFrom($user->plan) ?? Plan::Free)->requestsPerMinute();
    }
}
