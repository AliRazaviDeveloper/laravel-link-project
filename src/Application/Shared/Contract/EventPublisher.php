<?php

declare(strict_types=1);

namespace Shortwave\Application\Shared\Contract;

use Shortwave\Domain\Shared\Event\DomainEvent;

interface EventPublisher
{
    /**
     * @param  list<DomainEvent>  $events
     */
    public function publish(array $events): void;
}
