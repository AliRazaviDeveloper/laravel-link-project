<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Console\Command;

use Illuminate\Console\Command;
use Shortwave\Application\Link\Port\PendingClicks;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Throwable;

/**
 * Folds buffered click counts into the relational rows.
 *
 * This is the other half of the hot-path trade: redirects increment a Redis counter
 * instead of a database row, and this command pays the cost back in batches. It runs
 * every minute from the scheduler.
 *
 * The drain is destructive — the counts leave Redis the moment they are read — so a
 * failed write puts them back rather than dropping them. That makes the operation
 * at-least-once: a crash between the drain and the restore loses a batch, and a
 * retry after a partial write can double count. Both are acceptable for a click
 * total, and preferable to holding a Redis transaction open across a Postgres write.
 */
final class FlushClickCounters extends Command
{
    protected $signature = 'shortwave:flush-clicks
        {--batch=500 : how many links to reconcile in one pass}
        {--passes=10 : how many batches to run before yielding}';

    protected $description = 'Reconcile buffered click counters into the links table';

    public function handle(PendingClicks $pending, LinkRepository $links): int
    {
        $batch = max(1, (int) $this->option('batch'));
        $passes = max(1, (int) $this->option('passes'));
        $reconciled = 0;
        $clicks = 0;

        for ($pass = 0; $pass < $passes; $pass++) {
            $counts = $pending->drain($batch);

            if ($counts === []) {
                break;
            }

            try {
                $links->incrementClickCounts($counts);
            } catch (Throwable $exception) {
                // Hand the counts back before surfacing the failure, so the next run
                // picks them up instead of losing them.
                $pending->restore($counts);

                $this->components->error(sprintf(
                    'Flush failed after %d links; counts returned to the buffer: %s',
                    $reconciled,
                    $exception->getMessage(),
                ));

                return self::FAILURE;
            }

            $reconciled += count($counts);
            $clicks += array_sum($counts);
        }

        $this->components->info(sprintf('Reconciled %d clicks across %d links.', $clicks, $reconciled));

        return self::SUCCESS;
    }
}
