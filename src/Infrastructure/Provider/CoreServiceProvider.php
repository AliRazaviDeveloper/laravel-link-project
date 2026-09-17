<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Provider;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\ServiceProvider;
use Random\Randomizer;
use Shortwave\Application\Account\Port\PasswordHasher;
use Shortwave\Application\Account\Port\TokenIssuer;
use Shortwave\Application\Analytics\Service\StatsCache;
use Shortwave\Application\Link\Port\PendingClicks;
use Shortwave\Application\Link\Service\ResolutionCache;
use Shortwave\Application\Shared\Contract\CacheStore;
use Shortwave\Application\Shared\Contract\ClickRecorder;
use Shortwave\Application\Shared\Contract\EventPublisher;
use Shortwave\Application\Shared\Contract\IdentityGenerator;
use Shortwave\Application\Shared\Contract\SlugFactory;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Domain\Shared\Contract\Clock;
use Shortwave\Infrastructure\Cache\RedisCacheStore;
use Shortwave\Infrastructure\Cache\RedisPendingClicks;
use Shortwave\Infrastructure\Queue\BufferedClickRecorder;
use Shortwave\Infrastructure\Support\Base62SlugFactory;
use Shortwave\Infrastructure\Support\BcryptPasswordHasher;
use Shortwave\Infrastructure\Support\LaravelEventPublisher;
use Shortwave\Infrastructure\Support\SanctumTokenIssuer;
use Shortwave\Infrastructure\Support\SystemClock;
use Shortwave\Infrastructure\Support\UlidIdentityGenerator;
use Shortwave\Infrastructure\Support\UserAgentClassifier;
use Shortwave\Infrastructure\Support\VisitorFingerprinter;

/**
 * Binds the cross-cutting ports: time, identity, caching, hashing, telemetry.
 *
 * Persistence lives in its own provider so that swapping a store — or pointing the
 * test suite at a different one — touches one file.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(IdentityGenerator::class, UlidIdentityGenerator::class);
        $this->app->singleton(Randomizer::class, fn (): Randomizer => new Randomizer);

        $this->bindCaching();
        $this->bindIdentity();
        $this->bindTelemetry();
    }

    private function bindCaching(): void
    {
        $this->app->singleton(CacheStore::class, function ($app): CacheStore {
            // A dedicated store rather than the default one: the application cache
            // and the framework's own (sessions, locks) should not be able to evict
            // each other, and this one is configured with its own prefix.
            return new RedisCacheStore($app->make(CacheFactory::class)->store('shortwave'));
        });

        $this->app->singleton(ResolutionCache::class, function ($app): ResolutionCache {
            /** @var array{hit_ttl: int, absent_ttl: int} $config */
            $config = $app->make('config')->get('shortwave.cache.resolution');

            return new ResolutionCache(
                $app->make(CacheStore::class),
                $config['hit_ttl'],
                $config['absent_ttl'],
            );
        });

        $this->app->singleton(StatsCache::class);

        $this->app->singleton(PendingClicks::class, function ($app): PendingClicks {
            return new RedisPendingClicks(
                $app->make(RedisFactory::class),
                (string) $app->make('config')->get('shortwave.redis.connection', 'default'),
            );
        });
    }

    private function bindIdentity(): void
    {
        $this->app->singleton(PasswordHasher::class, function ($app): PasswordHasher {
            return new BcryptPasswordHasher($app->make(Hasher::class));
        });

        $this->app->singleton(TokenIssuer::class, function ($app): TokenIssuer {
            $minutes = $app->make('config')->get('sanctum.expiration');

            return new SanctumTokenIssuer($minutes === null ? null : (int) $minutes);
        });

        $this->app->singleton(SlugFactory::class, function ($app): SlugFactory {
            return new Base62SlugFactory(
                $app->make(LinkRepository::class),
                $app->make(Randomizer::class),
                (int) $app->make('config')->get('shortwave.slug.length', 7),
            );
        });
    }

    private function bindTelemetry(): void
    {
        $this->app->singleton(EventPublisher::class, function ($app): EventPublisher {
            return new LaravelEventPublisher($app->make(EventDispatcher::class));
        });

        $this->app->singleton(UserAgentClassifier::class);

        $this->app->singleton(VisitorFingerprinter::class, function ($app): VisitorFingerprinter {
            /** @var string $secret */
            $secret = $app->make('config')->get('shortwave.analytics.fingerprint_secret');

            return new VisitorFingerprinter($secret);
        });

        $this->app->singleton(BufferedClickRecorder::class, function ($app): BufferedClickRecorder {
            $config = $app->make('config');

            return new BufferedClickRecorder(
                $app->make(RedisFactory::class),
                $app->make(BusDispatcher::class),
                (int) $config->get('shortwave.analytics.flush_batch', 100),
                (string) $config->get('shortwave.redis.connection', 'default'),
            );
        });

        $this->app->alias(BufferedClickRecorder::class, ClickRecorder::class);
    }
}
