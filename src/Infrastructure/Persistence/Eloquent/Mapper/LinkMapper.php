<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Persistence\Eloquent\Mapper;

use DateTimeImmutable;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Link\Entity\Link;
use Shortwave\Domain\Link\Enum\LinkStatus;
use Shortwave\Domain\Link\ValueObject\Destination;
use Shortwave\Domain\Link\ValueObject\ExpiryPolicy;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Infrastructure\Persistence\Eloquent\Model\LinkModel;

/**
 * Translates between rows and the Link aggregate.
 *
 * Hydration goes through `reconstitute`, never through the named constructor:
 * stored rows are history and must load even when they would fail today's
 * validation — an expiry date that has since passed being the obvious case.
 */
final readonly class LinkMapper
{
    public function toDomain(LinkModel $model): Link
    {
        return Link::reconstitute(
            id: LinkId::fromString($model->id),
            accountId: AccountId::fromString($model->account_id),
            slug: Slug::fromString($model->slug),
            destination: Destination::fromString($model->destination_url),
            title: $model->title,
            status: LinkStatus::from($model->status),
            expiry: ExpiryPolicy::reconstitute(
                $model->expires_at?->toDateTimeImmutable(),
                $model->max_clicks,
            ),
            clickCount: $model->click_count,
            createdAt: $model->created_at->toDateTimeImmutable(),
            updatedAt: $model->updated_at->toDateTimeImmutable(),
        );
    }

    /**
     * Column values for an insert or update.
     *
     * `click_count` is absent on purpose: it is owned by the Redis counter and the
     * reconciliation job, so writing the aggregate's copy back here would undo
     * clicks recorded since the aggregate was loaded.
     *
     * @return array<string, string|int|DateTimeImmutable|null>
     */
    public function toColumns(Link $link): array
    {
        return [
            'id' => $link->id()->value,
            'account_id' => $link->accountId()->value,
            'slug' => $link->slug()->value,
            'destination_url' => $link->destination()->value,
            'destination_host' => $link->destination()->host,
            'title' => $link->title(),
            'status' => $link->status()->value,
            'expires_at' => $link->expiry()->expiresAt,
            'max_clicks' => $link->expiry()->maxClicks,
            'created_at' => $link->createdAt(),
            'updated_at' => $link->updatedAt(),
        ];
    }
}
