<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Handler;

use Shortwave\Application\Link\Command\ArchiveLink;
use Shortwave\Application\Link\Service\ResolutionCache;
use Shortwave\Application\Shared\Contract\EventPublisher;
use Shortwave\Application\Shared\Contract\TransactionManager;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Domain\Shared\Contract\Clock;

final readonly class ArchiveLinkHandler
{
    public function __construct(
        private LinkGuard $guard,
        private LinkRepository $links,
        private ResolutionCache $cache,
        private TransactionManager $transactions,
        private EventPublisher $events,
        private Clock $clock,
    ) {}

    public function handle(ArchiveLink $command): void
    {
        $link = $this->guard->ownedBy($command->accountId, $command->linkId);

        $link->archive($this->clock->now());

        $this->transactions->transactional(fn () => $this->links->save($link));

        $this->cache->forget($link->slug());

        $this->events->publish($link->releaseEvents());
    }
}
