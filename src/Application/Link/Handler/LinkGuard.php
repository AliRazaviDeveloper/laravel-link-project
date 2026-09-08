<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Handler;

use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Link\Entity\Link;
use Shortwave\Domain\Link\Exception\LinkNotFound;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Domain\Link\ValueObject\LinkId;

/**
 * Loads a link and proves the caller owns it.
 *
 * Every write handler needs this pair of checks, and a missing link and someone
 * else's link must be indistinguishable from outside — so both answer
 * LinkNotFound and the decision lives in exactly one place.
 */
final readonly class LinkGuard
{
    public function __construct(private LinkRepository $links) {}

    public function ownedBy(string $accountId, string $linkId): Link
    {
        $id = LinkId::fromString($linkId);
        $link = $this->links->findById($id);

        if ($link === null || ! $link->belongsTo(AccountId::fromString($accountId))) {
            throw LinkNotFound::withId($id);
        }

        return $link;
    }
}
