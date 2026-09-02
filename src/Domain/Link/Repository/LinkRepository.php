<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\Repository;

use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Link\Entity\Link;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Link\ValueObject\Slug;

/**
 * Write-side access to the link aggregate.
 *
 * Listing and filtering live in a separate read-model port: mixing paginated
 * projections in here would force every implementation, including the in-memory
 * one used by unit tests, to reimplement query semantics.
 */
interface LinkRepository
{
    public function findById(LinkId $id): ?Link;

    public function findBySlug(Slug $slug): ?Link;

    public function slugExists(Slug $slug): bool;

    /**
     * Persists a new aggregate.
     *
     * @throws \Shortwave\Domain\Link\Exception\SlugAlreadyTaken when the unique
     *                                                           slug constraint rejects the insert
     */
    public function add(Link $link): void;

    public function save(Link $link): void;

    public function remove(LinkId $id): void;

    /**
     * Folds buffered click counts into the aggregate without loading it.
     *
     * @param  array<string, int>  $increments  link id => clicks to add
     */
    public function incrementClickCounts(array $increments): void;

    public function countForAccount(AccountId $accountId): int;
}
