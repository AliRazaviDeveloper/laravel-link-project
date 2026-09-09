<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Persistence\Eloquent\Repository;

use Illuminate\Database\Eloquent\Builder;
use Shortwave\Application\Link\DTO\LinkFilter;
use Shortwave\Application\Link\Port\LinkCatalog;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Infrastructure\Persistence\Eloquent\Model\LinkModel;

final readonly class EloquentLinkCatalog implements LinkCatalog
{
    /**
     * Only the columns a list row needs. Selecting `*` would drag the full
     * destination URL of every row into memory for a page that shows a truncated
     * version of it.
     */
    private const array COLUMNS = [
        'id', 'slug', 'destination_url', 'title', 'status',
        'expires_at', 'max_clicks', 'click_count', 'created_at', 'updated_at',
    ];

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function search(AccountId $accountId, LinkFilter $filter): array
    {
        $query = $this->baseQuery($accountId, $filter);

        // Counted before pagination is applied, and on a clone so the ordering and
        // limit below do not affect the total.
        $total = (clone $query)->toBase()->getCountForPagination();

        $rows = $query
            ->orderBy($filter->sortBy, $filter->descending ? 'desc' : 'asc')
            // ULIDs are time-sortable, so this tiebreak keeps pages stable when
            // many rows share a timestamp — without it, a row can appear on two
            // consecutive pages or on neither.
            ->orderBy('id', $filter->descending ? 'desc' : 'asc')
            ->forPage($filter->page, $filter->perPage)
            ->get(self::COLUMNS);

        return [
            'rows' => array_values(array_map($this->toRow(...), $rows->all())),
            'total' => $total,
        ];
    }

    /**
     * @param  list<string>  $linkIds
     * @return array<string, array<string, mixed>>
     */
    public function findManyById(AccountId $accountId, array $linkIds): array
    {
        if ($linkIds === []) {
            return [];
        }

        $rows = LinkModel::query()
            ->where('account_id', $accountId->value)
            ->whereIn('id', $linkIds)
            ->get(['id', 'slug', 'title']);

        $keyed = [];

        foreach ($rows as $row) {
            $keyed[$row->id] = ['id' => $row->id, 'slug' => $row->slug, 'title' => $row->title];
        }

        return $keyed;
    }

    /**
     * @return Builder<LinkModel>
     */
    private function baseQuery(AccountId $accountId, LinkFilter $filter): Builder
    {
        $query = LinkModel::query()->where('account_id', $accountId->value);

        if ($filter->status !== null) {
            $query->where('status', $filter->status->value);
        }

        if ($filter->search !== null) {
            $term = $this->escapeLike($filter->search);

            $query->where(function (Builder $scoped) use ($term): void {
                $scoped
                    ->where('slug', 'like', $term.'%')
                    ->orWhere('title', 'ilike', '%'.$term.'%')
                    ->orWhere('destination_host', 'like', '%'.$term.'%');
            });
        }

        return $query;
    }

    /**
     * Neutralises LIKE wildcards in user input so a search for "100%" does not
     * match every row.
     */
    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(LinkModel $model): array
    {
        return [
            'id' => $model->id,
            'slug' => $model->slug,
            'destination_url' => $model->destination_url,
            'title' => $model->title,
            'status' => $model->status,
            'expires_at' => $model->expires_at?->toDateTimeImmutable(),
            'max_clicks' => $model->max_clicks,
            'click_count' => $model->click_count,
            'created_at' => $model->created_at->toDateTimeImmutable(),
            'updated_at' => $model->updated_at->toDateTimeImmutable(),
        ];
    }
}
