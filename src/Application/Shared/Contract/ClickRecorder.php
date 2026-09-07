<?php

declare(strict_types=1);

namespace Shortwave\Application\Shared\Contract;

use Shortwave\Application\Analytics\DTO\ClickContext;

/**
 * Accepts a click and returns immediately.
 *
 * The redirect path must not wait on the analytics store, so implementations
 * buffer in Redis and let a worker drain the buffer into MongoDB in batches.
 */
interface ClickRecorder
{
    public function record(ClickContext $context): void;
}
