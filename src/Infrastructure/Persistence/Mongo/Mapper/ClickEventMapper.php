<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Persistence\Mongo\Mapper;

use MongoDB\BSON\UTCDateTime;
use Shortwave\Domain\Analytics\Entity\ClickEvent;

final readonly class ClickEventMapper
{
    /**
     * The `_id` is our own ULID rather than a generated ObjectId. Both sort by
     * creation time, but reusing the domain identifier means an event can be
     * correlated across the two stores without a second index.
     *
     * @return array<string, mixed>
     */
    public static function toDocument(ClickEvent $event): array
    {
        return [
            '_id' => $event->id->value,
            'link_id' => $event->linkId->value,
            'account_id' => $event->accountId->value,
            'occurred_at' => new UTCDateTime($event->occurredAt),
            'visitor' => $event->visitor->hash,
            'device' => [
                'type' => $event->device->type->value,
                'browser' => $event->device->browser,
                'platform' => $event->device->platform,
            ],
            'referrer' => ['host' => $event->referrer->host],
            'geo' => ['country' => $event->geo->country],
        ];
    }
}
