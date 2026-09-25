<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Handler;

use DateTimeImmutable;
use Shortwave\Application\Link\DTO\LinkPage;
use Shortwave\Application\Link\DTO\LinkView;
use Shortwave\Application\Link\Port\LinkCatalog;
use Shortwave\Application\Link\Port\PendingClicks;
use Shortwave\Application\Link\Query\ListLinks;
use Shortwave\Application\Shared\Support\Input;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Link\Enum\LinkStatus;
use Shortwave\Domain\Shared\Contract\Clock;

/**
 * Lists links straight from projection rows.
 *
 * Buffered Redis counts are added on top of each stored total so the list agrees
 * with the redirect endpoint. Reading them is one pipelined batch, not a call per
 * row, because a 100-item page would otherwise cost 100 round trips.
 */
final readonly class ListLinksHandler
{
    public function __construct(
        private LinkCatalog $catalog,
        private PendingClicks $pending,
        private Clock $clock,
        private string $shortDomain,
    ) {}

    public function handle(ListLinks $query): LinkPage
    {
        $result = $this->catalog->search(
            AccountId::fromString($query->accountId),
            $query->filter,
        );

        $now = $this->clock->now();
        $pending = $this->pending->pendingForMany(array_map(
            static fn (array $row): string => Input::string($row, 'id'),
            $result['rows'],
        ));

        $items = array_map(
            fn (array $row): LinkView => $this->toView($row, $pending, $now),
            $result['rows'],
        );

        return new LinkPage(
            items: array_values($items),
            total: $result['total'],
            page: $query->filter->page,
            perPage: $query->filter->perPage,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $pending  buffered click counts by link id
     */
    private function toView(array $row, array $pending, DateTimeImmutable $now): LinkView
    {
        $linkId = Input::string($row, 'id');
        $slug = Input::string($row, 'slug');
        $expiresAt = Input::nullableDateTime($row, 'expires_at');
        $maxClicks = Input::nullableInt($row, 'max_clicks');
        $status = LinkStatus::from(Input::string($row, 'status'));
        $clicks = Input::int($row, 'click_count') + ($pending[$linkId] ?? 0);

        return new LinkView(
            id: $linkId,
            slug: $slug,
            shortUrl: rtrim($this->shortDomain, '/').'/'.$slug,
            destinationUrl: Input::string($row, 'destination_url'),
            title: Input::nullableString($row, 'title'),
            status: $status,
            expiresAt: $expiresAt,
            maxClicks: $maxClicks,
            clickCount: $clicks,
            resolvable: $this->isResolvable($status, $expiresAt, $maxClicks, $clicks, $now),
            createdAt: Input::dateTime($row, 'created_at'),
            updatedAt: Input::dateTime($row, 'updated_at'),
        );
    }

    /**
     * Mirrors Link::isResolvableAt for projection rows.
     *
     * Duplicating the rule is a knowing trade: hydrating a full aggregate per row
     * costs more than a list endpoint should, and the domain still owns the single
     * version that decides whether traffic actually flows. A change to expiry rules
     * has to touch both, which is why the unit tests assert the two agree.
     */
    private function isResolvable(
        LinkStatus $status,
        ?DateTimeImmutable $expiresAt,
        ?int $maxClicks,
        int $clicks,
        DateTimeImmutable $now,
    ): bool {
        return $status->isActive()
            && ($expiresAt === null || $now < $expiresAt)
            && ($maxClicks === null || $clicks < $maxClicks);
    }
}
