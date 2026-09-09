<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\DatabaseManager;
use Shortwave\Application\Shared\Contract\TransactionManager;

final readonly class DatabaseTransactionManager implements TransactionManager
{
    /**
     * Deadlock retries are left to the caller. Retrying here would silently
     * re-run a closure that may have already queued a job or sent a mail.
     */
    public function __construct(private DatabaseManager $database) {}

    public function transactional(callable $work): mixed
    {
        // The connection is handed to the callback by the framework; our port does not
        // expose it, because a use case that reaches for a connection has stopped being
        // storage-agnostic.
        return $this->database->connection()->transaction(
            static fn (): mixed => $work(),
        );
    }

    public function afterCommit(callable $work): void
    {
        $this->database->connection()->afterCommit(static function () use ($work): void {
            $work();
        });
    }
}
