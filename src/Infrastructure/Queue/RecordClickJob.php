<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Queue;

use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Shortwave\Application\Shared\Contract\IdentityGenerator;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Analytics\Entity\ClickEvent;
use Shortwave\Domain\Analytics\Enum\DeviceType;
use Shortwave\Domain\Analytics\Repository\ClickEventRepository;
use Shortwave\Domain\Analytics\ValueObject\ClickEventId;
use Shortwave\Domain\Analytics\ValueObject\DeviceProfile;
use Shortwave\Domain\Analytics\ValueObject\GeoLocation;
use Shortwave\Domain\Analytics\ValueObject\Referrer;
use Shortwave\Domain\Analytics\ValueObject\VisitorFingerprint;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Throwable;

/**
 * Writes a batch of buffered clicks into the analytics store.
 *
 * Batching is the whole point: one `insertMany` of a few hundred documents costs
 * roughly what a single insert does, and the redirect path never waits for either.
 *
 * Payloads are plain arrays rather than serialised value objects. A job sitting in
 * the queue across a deploy must still be readable by the new code, and PHP
 * serialisation of objects makes that a coin flip.
 */
final class RecordClickJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * A click is worth a few seconds of retrying and no more; holding a job past
     * that only delays the rest of the queue.
     */
    public int $timeout = 30;

    /**
     * @param  list<array<string, string|int>>  $rows
     */
    public function __construct(private readonly array $rows)
    {
        $this->onQueue('analytics');
    }

    public function handle(
        ClickEventRepository $clicks,
        IdentityGenerator $identities,
    ): void {
        $events = [];

        foreach ($this->rows as $row) {
            $event = $this->toEvent($row, $identities);

            // One malformed row must not poison a batch of hundreds; it is dropped
            // and the rest are written.
            if ($event !== null) {
                $events[] = $event;
            }
        }

        $clicks->appendMany($events);
    }

    /**
     * @param  array<string, string|int>  $row
     */
    private function toEvent(array $row, IdentityGenerator $identities): ?ClickEvent
    {
        try {
            return new ClickEvent(
                id: ClickEventId::fromString($identities->next()),
                linkId: LinkId::fromString((string) $row['link_id']),
                accountId: AccountId::fromString((string) $row['account_id']),
                occurredAt: new DateTimeImmutable((string) $row['occurred_at']),
                visitor: VisitorFingerprint::fromHash((string) $row['visitor']),
                device: DeviceProfile::of(
                    DeviceType::tryFrom((string) $row['device_type']) ?? DeviceType::Unknown,
                    (string) $row['browser'],
                    (string) $row['platform'],
                ),
                referrer: Referrer::fromHost((string) $row['referrer']),
                geo: GeoLocation::fromCountryCode((string) $row['country']),
            );
        } catch (Throwable) {
            return null;
        }
    }
}
