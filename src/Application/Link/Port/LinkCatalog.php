<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Port;

use Shortwave\Application\Link\DTO\LinkFilter;
use Shortwave\Domain\Account\ValueObject\AccountId;

/**
 * Read-side counterpart to LinkRepository.
 *
 * Returns rows, not aggregates: hydrating 25 Link entities to serialise a list
 * page would run the invariants of every value object for no benefit.
 */
interface LinkCatalog
{
    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function search(AccountId $accountId, LinkFilter $filter): array;

    /**
     * @param  list<string>  $linkIds
     * @return array<string, array<string, mixed>> keyed by link id
     */
    public function findManyById(AccountId $accountId, array $linkIds): array;
}
