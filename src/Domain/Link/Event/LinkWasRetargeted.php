<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\Event;

use DateTimeImmutable;
use Shortwave\Domain\Link\ValueObject\Destination;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Domain\Shared\Event\DomainEvent;

final readonly class LinkWasRetargeted implements DomainEvent
{
    public function __construct(
        public LinkId $linkId,
        public Slug $slug,
        public Destination $previousDestination,
        public Destination $destination,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function name(): string
    {
        return 'link.retargeted';
    }

    public function payload(): array
    {
        return [
            'link_id' => $this->linkId->value,
            'slug' => $this->slug->value,
            'from' => $this->previousDestination->value,
            'to' => $this->destination->value,
        ];
    }
}
