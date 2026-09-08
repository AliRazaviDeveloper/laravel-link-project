<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Handler;

use DateTimeImmutable;
use Shortwave\Application\Link\Command\UpdateLink;
use Shortwave\Application\Link\DTO\LinkView;
use Shortwave\Application\Link\Service\ResolutionCache;
use Shortwave\Application\Shared\Contract\EventPublisher;
use Shortwave\Application\Shared\Contract\TransactionManager;
use Shortwave\Application\Shared\Support\Timestamp;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Domain\Link\ValueObject\Destination;
use Shortwave\Domain\Link\ValueObject\ExpiryPolicy;
use Shortwave\Domain\Shared\Contract\Clock;

final readonly class UpdateLinkHandler
{
    public function __construct(
        private LinkGuard $guard,
        private LinkRepository $links,
        private ResolutionCache $cache,
        private TransactionManager $transactions,
        private EventPublisher $events,
        private Clock $clock,
        private string $shortDomain,
    ) {}

    public function handle(UpdateLink $command): LinkView
    {
        $link = $this->guard->ownedBy($command->accountId, $command->linkId);
        $now = $this->clock->now();

        if ($command->destinationUrl !== null) {
            $link->retargetTo(Destination::fromString($command->destinationUrl), $now);
        }

        if ($command->titleProvided) {
            $link->rename($command->title, $now);
        }

        if ($command->touchesExpiry()) {
            $link->applyExpiry($this->mergeExpiry($command, $link->expiry(), $now), $now);
        }

        $this->transactions->transactional(fn () => $this->links->save($link));

        // Dropped after the commit: evicting earlier would let a concurrent
        // redirect repopulate the cache from the pre-update row.
        $this->cache->forget($link->slug());

        $this->events->publish($link->releaseEvents());

        return LinkView::fromEntity($link, $this->shortDomain, $now);
    }

    /**
     * Fields the caller left out keep their current value; fields sent as null
     * are cleared.
     */
    private function mergeExpiry(
        UpdateLink $command,
        ExpiryPolicy $current,
        DateTimeImmutable $now,
    ): ExpiryPolicy {
        $expiresAt = $command->expiresAtProvided
            ? Timestamp::parseOptional($command->expiresAt, 'expires_at')
            : $current->expiresAt;

        $maxClicks = $command->maxClicksProvided
            ? $command->maxClicks
            : $current->maxClicks;

        // An unchanged past expiry must survive an unrelated edit, so only a
        // freshly supplied date is held to the "must be future" rule.
        if (! $command->expiresAtProvided) {
            return ExpiryPolicy::reconstitute($expiresAt, $maxClicks);
        }

        return ExpiryPolicy::of($expiresAt, $maxClicks, $now);
    }
}
