<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Port;

use Shortwave\Domain\Link\ValueObject\LinkId;

/**
 * Click counting on the hot path.
 *
 * A relational `UPDATE ... SET click_count = click_count + 1` per redirect
 * serialises all traffic for a viral link behind one row lock. Counting in memory
 * instead makes a redirect a single atomic increment, and a scheduled job folds the
 * buffered totals back into the relational row.
 *
 * The consequence is a bounded lag between the counter a client reads and the
 * clicks already served. Every read path adds `pendingFor` on top of the stored
 * total to hide that lag, and the API documents it rather than pretending the
 * number is transactional.
 */
interface PendingClicks
{
    public function increment(LinkId $linkId): int;

    public function pendingFor(LinkId $linkId): int;

    /**
     * @param  list<string>  $linkIds
     * @return array<string, int> link id => buffered clicks, zeroes included
     */
    public function pendingForMany(array $linkIds): array;

    /**
     * Atomically takes up to `$limit` links' buffered counts and resets them.
     *
     * The caller owns those counts from that moment on: if the relational write
     * fails, it must hand them back via `restore()` or they are lost.
     *
     * @return array<string, int> link id => clicks drained
     */
    public function drain(int $limit): array;

    /**
     * Returns drained counts to the buffer after a failed flush.
     *
     * @param  array<string, int>  $counts
     */
    public function restore(array $counts): void;
}
