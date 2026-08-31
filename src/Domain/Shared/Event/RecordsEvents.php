<?php

declare(strict_types=1);

namespace Shortwave\Domain\Shared\Event;

/**
 * Lets an aggregate collect events without knowing how they are dispatched.
 */
trait RecordsEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /**
     * Hands over the pending events and clears the buffer, so flushing twice
     * cannot publish the same event twice.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    protected function recordEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
