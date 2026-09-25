<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Handler;

use Shortwave\Application\Link\DTO\LinkView;
use Shortwave\Application\Link\Port\PendingClicks;
use Shortwave\Application\Link\Query\ShowLink;
use Shortwave\Domain\Shared\Contract\Clock;

final readonly class ShowLinkHandler
{
    public function __construct(
        private LinkGuard $guard,
        private PendingClicks $pending,
        private Clock $clock,
        private string $shortDomain,
    ) {}

    public function handle(ShowLink $query): LinkView
    {
        $link = $this->guard->ownedBy($query->accountId, $query->linkId);

        // Fold in the unreconciled Redis count so a client that just generated
        // traffic does not see a stale total.
        $pending = $this->pending->pendingFor($link->id());

        for ($i = 0; $i < $pending; $i++) {
            $link->registerClick();
        }

        return LinkView::fromEntity($link, $this->shortDomain, $this->clock->now());
    }
}
