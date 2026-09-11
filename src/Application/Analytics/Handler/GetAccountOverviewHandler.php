<?php

declare(strict_types=1);

namespace Shortwave\Application\Analytics\Handler;

use Shortwave\Application\Analytics\DTO\AccountOverviewView;
use Shortwave\Application\Analytics\Query\GetAccountOverview;
use Shortwave\Application\Link\Port\LinkCatalog;
use Shortwave\Application\Shared\Support\Input;
use Shortwave\Domain\Account\Exception\AccountNotFound;
use Shortwave\Domain\Account\Repository\AccountRepository;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Analytics\Repository\ClickEventRepository;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Domain\Shared\Contract\Clock;
use Shortwave\Domain\Shared\ValueObject\DateRange;

/**
 * The dashboard's landing query: one call, both stores.
 *
 * Totals and the busiest links come from MongoDB, the link inventory and plan
 * allowance from Postgres, and the two are stitched here rather than in a view.
 * Mongo only knows link ids, so slugs and titles are fetched in a single batched
 * lookup — the alternative is a query per top link.
 */
final readonly class GetAccountOverviewHandler
{
    private const int TOP_LINK_COUNT = 5;

    public function __construct(
        private AccountRepository $accounts,
        private LinkRepository $links,
        private LinkCatalog $catalog,
        private ClickEventRepository $clicks,
        private Clock $clock,
    ) {}

    public function handle(GetAccountOverview $query): AccountOverviewView
    {
        $accountId = AccountId::fromString($query->accountId);
        $account = $this->accounts->findById($accountId);

        if ($account === null) {
            throw AccountNotFound::withId($accountId);
        }

        $now = $this->clock->now();
        $days = min($query->days, $account->plan()->analyticsRetentionDays());
        $range = DateRange::lastDays($days, $now);

        $ranking = $this->clicks->topLinksForAccount($accountId, $range, self::TOP_LINK_COUNT);

        return new AccountOverviewView(
            from: $range->from,
            until: $range->until,
            totals: $this->clicks->totalsForAccount($accountId, $range),
            linkCount: $this->links->countForAccount($accountId),
            planLinkAllowance: $account->plan()->linkAllowance(),
            topLinks: $this->describeRanking($accountId, $ranking),
        );
    }

    /**
     * @param  array<string, int>  $ranking  link id => clicks
     * @return list<array{link_id: string, slug: string, title: string|null, clicks: int}>
     */
    private function describeRanking(AccountId $accountId, array $ranking): array
    {
        if ($ranking === []) {
            return [];
        }

        $rows = $this->catalog->findManyById($accountId, array_map(strval(...), array_keys($ranking)));
        $described = [];

        foreach ($ranking as $linkId => $clicks) {
            $row = $rows[$linkId] ?? null;

            // A ranked id with no row means the link was deleted after its clicks
            // were recorded. Skipping it is right: we cannot label it, and showing
            // a bare id in a "top links" list helps nobody.
            if ($row === null) {
                continue;
            }

            $described[] = [
                'link_id' => (string) $linkId,
                'slug' => Input::string($row, 'slug'),
                'title' => Input::nullableString($row, 'title'),
                'clicks' => $clicks,
            ];
        }

        return $described;
    }
}
