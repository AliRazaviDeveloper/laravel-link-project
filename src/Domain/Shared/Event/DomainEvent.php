<?php

declare(strict_types=1);

namespace Shortwave\Domain\Shared\Event;

use DateTimeImmutable;

interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;

    public function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
