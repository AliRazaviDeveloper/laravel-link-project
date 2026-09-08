<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Handler;

use Shortwave\Application\Link\Command\RestoreLink;
use Shortwave\Application\Link\DTO\LinkView;
use Shortwave\Application\Link\Service\ResolutionCache;
use Shortwave\Application\Shared\Contract\TransactionManager;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Domain\Shared\Contract\Clock;

final readonly class RestoreLinkHandler
{
    public function __construct(
        private LinkGuard $guard,
        private LinkRepository $links,
        private ResolutionCache $cache,
        private TransactionManager $transactions,
        private Clock $clock,
        private string $shortDomain,
    ) {}

    public function handle(RestoreLink $command): LinkView
    {
        $link = $this->guard->ownedBy($command->accountId, $command->linkId);
        $now = $this->clock->now();

        $link->restore($now);

        $this->transactions->transactional(fn () => $this->links->save($link));

        // An archived slug is cached as a negative result; clear it so the link
        // starts serving again immediately.
        $this->cache->forget($link->slug());

        return LinkView::fromEntity($link, $this->shortDomain, $now);
    }
}
