<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Persistence\Eloquent\Repository;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Link\Entity\Link;
use Shortwave\Domain\Link\Exception\SlugAlreadyTaken;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Infrastructure\Persistence\Eloquent\Mapper\LinkMapper;
use Shortwave\Infrastructure\Persistence\Eloquent\Model\LinkModel;

final readonly class EloquentLinkRepository implements LinkRepository
{
    public function __construct(
        private LinkMapper $mapper,
        private ConnectionInterface $connection,
    ) {}

    public function findById(LinkId $id): ?Link
    {
        $model = LinkModel::query()->find($id->value);

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function findBySlug(Slug $slug): ?Link
    {
        $model = LinkModel::query()->where('slug', $slug->value)->first();

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function slugExists(Slug $slug): bool
    {
        return LinkModel::query()->where('slug', $slug->value)->exists();
    }

    public function add(Link $link): void
    {
        try {
            LinkModel::query()->create($this->mapper->toColumns($link));
        } catch (UniqueConstraintViolationException) {
            // The pre-check in the handler narrows the window but cannot close it;
            // the index is the real arbiter, so its verdict is translated here
            // rather than leaking a driver exception to the caller.
            throw SlugAlreadyTaken::for($link->slug());
        }
    }

    public function save(Link $link): void
    {
        $columns = $this->mapper->toColumns($link);
        unset($columns['id'], $columns['created_at']);

        LinkModel::query()->whereKey($link->id()->value)->update($columns);
    }

    public function remove(LinkId $id): void
    {
        LinkModel::query()->whereKey($id->value)->delete();
    }

    /**
     * Folds buffered counts in as a single statement.
     *
     * A CASE expression keeps this to one round trip regardless of batch size, and
     * the addition happens in the database so a concurrent flush cannot clobber
     * another worker's increment the way a read-modify-write would.
     *
     * @param  array<string, int>  $increments
     */
    public function incrementClickCounts(array $increments): void
    {
        $increments = array_filter($increments, static fn (int $delta): bool => $delta > 0);

        if ($increments === []) {
            return;
        }

        $ids = array_keys($increments);
        $cases = [];
        $bindings = [];

        foreach ($increments as $linkId => $delta) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $linkId;
            $bindings[] = $delta;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $this->connection->update(
            sprintf(
                'UPDATE links SET click_count = click_count + CASE id %s ELSE 0 END WHERE id IN (%s)',
                implode(' ', $cases),
                $placeholders,
            ),
            [...$bindings, ...$ids],
        );
    }

    public function countForAccount(AccountId $accountId): int
    {
        return LinkModel::query()->where('account_id', $accountId->value)->count();
    }
}
