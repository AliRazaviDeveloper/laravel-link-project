<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Support;

use Illuminate\Contracts\Events\Dispatcher;
use Shortwave\Application\Shared\Contract\EventPublisher;

/**
 * Publishes domain events onto the framework's dispatcher.
 *
 * Events are dispatched under their own `name()` as well as their class, so a
 * listener can subscribe to `link.created` without importing a domain class, and
 * the wire name stays stable if the class is ever moved.
 */
final readonly class LaravelEventPublisher implements EventPublisher
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
            $this->dispatcher->dispatch($event->name(), [$event]);
        }
    }
}
