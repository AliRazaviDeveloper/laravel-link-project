<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\Event;

use DateTimeImmutable;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Link\ValueObject\Destination;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Domain\Shared\Event\DomainEvent;

final readonly class LinkWasCreated implements DomainEvent
{
    public function __construct(
        public LinkId $linkId,
        public AccountId $accountId,
        public Slug $slug,
        public Destination $destination,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function name(): string
    {
        return 'link.created';
    }

    public function payload(): array
    {
        return [
            'link_id' => $this->linkId->value,
            'account_id' => $this->accountId->value,
            'slug' => $this->slug->value,
            'destination_url' => $this->destination->value,
        ];
    }
}
