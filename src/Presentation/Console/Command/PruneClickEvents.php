<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Console\Command;

use Illuminate\Console\Command;
use Shortwave\Application\Analytics\Service\StatsCache;
use Shortwave\Domain\Analytics\Repository\ClickEventRepository;
use Shortwave\Domain\Shared\Contract\Clock;

/**
 * Deletes click documents past the retention window.
 *
 * The TTL index already expires documents on its own; this exists for the case the
 * index cannot handle — shortening the retention window, where MongoDB's background
 * TTL thread can take a long time to catch up on a large backlog.
 *
 * Cached reports are flushed afterwards, since a report built before the purge would
 * otherwise keep reporting clicks that no longer exist.
 */
final class PruneClickEvents extends Command
{
    protected $signature = 'shortwave:prune-clicks
        {--days= : retention window in days, defaults to the configured value}
        {--dry-run : report what would be deleted without deleting it}';

    protected $description = 'Delete click events older than the retention window';

    public function handle(
        ClickEventRepository $clicks,
        StatsCache $cache,
        Clock $clock,
    ): int {
        $requested = $this->option('days');
        $configured = config('shortwave.analytics.retention_days', 400);

        $days = match (true) {
            is_numeric($requested) => (int) $requested,
            is_numeric($configured) => (int) $configured,
            default => 400,
        };

        if ($days < 1) {
            $this->components->error('The retention window must be at least one day.');

            return self::FAILURE;
        }

        $threshold = $clock->now()->modify(sprintf('-%d days', $days));

        if ($this->option('dry-run')) {
            $this->components->info(sprintf(
                'Would delete click events recorded before %s.',
                $threshold->format('Y-m-d H:i:s'),
            ));

            return self::SUCCESS;
        }

        $deleted = $clicks->purgeOlderThan($threshold);

        if ($deleted > 0) {
            $cache->flushAll();
        }

        $this->components->info(sprintf('Deleted %d click events older than %d days.', $deleted, $days));

        return self::SUCCESS;
    }
}
