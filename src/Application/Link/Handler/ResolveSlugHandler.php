<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Handler;

use Psr\Log\LoggerInterface;
use Shortwave\Application\Analytics\DTO\ClickContext;
use Shortwave\Application\Link\DTO\Resolution;
use Shortwave\Application\Link\Port\PendingClicks;
use Shortwave\Application\Link\Query\ResolveSlug;
use Shortwave\Application\Link\Service\ResolutionCache;
use Shortwave\Application\Shared\Contract\ClickRecorder;
use Shortwave\Domain\Link\Entity\Link;
use Shortwave\Domain\Link\Exception\LinkNotFound;
use Shortwave\Domain\Link\Exception\LinkNotResolvable;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Domain\Shared\Contract\Clock;
use Throwable;

/**
 * The redirect path.
 *
 * Every millisecond here is paid by a person waiting on a browser hop, so the
 * target is one Redis round trip in the steady state and no relational query at
 * all. The order of work is what buys that:
 *
 *  - the cache answers first, and only ever holds links that were resolvable when
 *    stored, so a hit needs no revalidation;
 *  - a miss loads the aggregate and lets the domain decide, which keeps expiry and
 *    click-cap interpretation in one place;
 *  - counting and analytics happen last and are never allowed to fail a redirect.
 *
 * Click-capped links are excluded from the cache: their resolvability depends on a
 * counter that moves underneath us, so they re-read every time. That cost applies
 * only to links that asked for a cap.
 */
final readonly class ResolveSlugHandler
{
    public function __construct(
        private LinkRepository $links,
        private ResolutionCache $cache,
        private PendingClicks $pending,
        private ClickRecorder $recorder,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws LinkNotFound the slug has never existed
     * @throws LinkNotResolvable it exists but is archived, expired or capped out
     */
    public function handle(ResolveSlug $query, ClickContext $context): Resolution
    {
        $slug = Slug::fromString($query->slug);
        $lookup = $this->cache->lookup($slug);

        if ($lookup->isKnownAbsent()) {
            throw LinkNotFound::withSlug($slug);
        }

        $resolution = $lookup->resolution ?? $this->loadAndCache($slug);

        $this->registerClick($resolution, $context);

        return $resolution;
    }

    private function loadAndCache(Slug $slug): Resolution
    {
        $link = $this->links->findBySlug($slug);

        if ($link === null) {
            $this->cache->storeAbsent($slug);

            throw LinkNotFound::withSlug($slug);
        }

        $destination = $this->applyPendingClicks($link)->resolve($this->clock->now());

        $resolution = new Resolution(
            linkId: $link->id()->value,
            accountId: $link->accountId()->value,
            destinationUrl: $destination->value,
        );

        // Archived and expired links are left uncached on purpose: caching them as
        // absent would lose the reason, and a 410 is cheap enough to serve from
        // Postgres for the small share of traffic that hits a retired link.
        if ($link->expiry()->maxClicks === null) {
            $this->cache->store($slug, $resolution, $link->expiry()->secondsUntilExpiry($this->clock->now()));
        }

        return $resolution;
    }

    /**
     * Folds Redis-buffered clicks into the aggregate before the cap is evaluated.
     * Without this a limit of 100 would let through everything counted since the
     * last reconciliation run.
     */
    private function applyPendingClicks(Link $link): Link
    {
        if ($link->expiry()->maxClicks === null) {
            return $link;
        }

        $pending = $this->pending->pendingFor($link->id());

        for ($i = 0; $i < $pending; $i++) {
            $link->registerClick();
        }

        return $link;
    }

    /**
     * A click we failed to record is a reporting gap; a 500 here is a broken link.
     * The trade is made explicitly, and loudly enough to alert on.
     */
    private function registerClick(Resolution $resolution, ClickContext $context): void
    {
        try {
            $this->pending->increment(LinkId::fromString($resolution->linkId));
            $this->recorder->record($context->forLink($resolution->linkId, $resolution->accountId));
        } catch (Throwable $exception) {
            $this->logger->error('Click telemetry dropped for a served redirect.', [
                'link_id' => $resolution->linkId,
                'exception' => $exception,
            ]);
        }
    }
}
