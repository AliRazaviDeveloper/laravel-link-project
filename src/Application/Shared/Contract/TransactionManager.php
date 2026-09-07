<?php

declare(strict_types=1);

namespace Shortwave\Application\Shared\Contract;

/**
 * Wraps relational writes. The analytics store is intentionally outside any
 * transaction: it is append-only and eventually consistent by design, so
 * enlisting it would buy atomicity we do not need at a latency cost we do.
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function transactional(callable $work): mixed;

    /**
     * Defers work until the surrounding transaction commits, so a queued job can
     * never observe a row that was rolled back.
     */
    public function afterCommit(callable $work): void;
}
