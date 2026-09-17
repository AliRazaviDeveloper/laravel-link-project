<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Provider;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use MongoDB\Laravel\Connection as MongoConnection;
use RuntimeException;
use Shortwave\Application\Account\Port\CredentialStore;
use Shortwave\Application\Link\Port\LinkCatalog;
use Shortwave\Application\Shared\Contract\TransactionManager;
use Shortwave\Domain\Account\Repository\AccountRepository;
use Shortwave\Domain\Analytics\Repository\ClickEventRepository;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Infrastructure\Persistence\Eloquent\DatabaseTransactionManager;
use Shortwave\Infrastructure\Persistence\Eloquent\Mapper\AccountMapper;
use Shortwave\Infrastructure\Persistence\Eloquent\Mapper\LinkMapper;
use Shortwave\Infrastructure\Persistence\Eloquent\Repository\EloquentAccountRepository;
use Shortwave\Infrastructure\Persistence\Eloquent\Repository\EloquentLinkCatalog;
use Shortwave\Infrastructure\Persistence\Eloquent\Repository\EloquentLinkRepository;
use Shortwave\Infrastructure\Persistence\Mongo\Repository\MongoClickEventRepository;

/**
 * Wires the two stores behind their ports.
 *
 * Postgres holds the aggregates that need transactions and unique constraints;
 * MongoDB holds the append-only click log that would make those constraints
 * expensive and never needs them. Everything above this file addresses both
 * through interfaces and cannot tell which is which.
 */
final class PersistenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LinkMapper::class);
        $this->app->singleton(AccountMapper::class);

        $this->app->singleton(LinkRepository::class, function ($app): LinkRepository {
            return new EloquentLinkRepository(
                $app->make(LinkMapper::class),
                $app->make(DatabaseManager::class)->connection(),
            );
        });

        $this->app->singleton(EloquentAccountRepository::class);
        $this->app->alias(EloquentAccountRepository::class, AccountRepository::class);
        $this->app->alias(EloquentAccountRepository::class, CredentialStore::class);

        $this->app->singleton(LinkCatalog::class, EloquentLinkCatalog::class);
        $this->app->singleton(TransactionManager::class, DatabaseTransactionManager::class);

        $this->app->singleton(ClickEventRepository::class, function ($app): ClickEventRepository {
            $connection = $app->make(DatabaseManager::class)->connection('mongodb');

            if (! $connection instanceof MongoConnection) {
                throw new RuntimeException(
                    'The "mongodb" database connection is not a MongoDB connection; check config/database.php.',
                );
            }

            return new MongoClickEventRepository($connection);
        });
    }
}
